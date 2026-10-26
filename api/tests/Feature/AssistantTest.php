<?php

namespace Tests\Feature;

use App\Ai\Llm\FakeLlmClient;
use App\Ai\Llm\LlmException;
use App\Ai\Llm\LlmRequest;
use App\Ai\TokenBudget;
use App\Domain\AlarmKind;
use App\Domain\Severity;
use App\Models\AiUsage;
use App\Models\Alarm;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FakeLlmClient::reset();
        Artisan::call('knowledge:ingest', ['--sync' => true]);
    }

    /** @return list<array{event: string, data: array<string, mixed>}> */
    private function ask(string $email, string $message, array $focus = []): array
    {
        $id = $this->as($email)->postJson('/api/assistant/conversations', $focus)->assertCreated()->json('conversation.id');
        $body = $this->as($email)->post("/api/assistant/conversations/{$id}/messages", ['message' => $message], ['Accept' => 'text/event-stream'])
            ->assertOk()->streamedContent();

        preg_match_all('/event: (\w+)\ndata: (.*)\n\n/', $body, $m, PREG_SET_ORDER);

        return array_map(fn ($x) => ['event' => $x[1], 'data' => json_decode($x[2], true)], $m);
    }

    public function test_explain_alarm_runs_tools_and_cites_documentation(): void
    {
        $alarm = Alarm::query()->create([
            'crane_id' => 'c-101', 'tenant_id' => 't-budinvest', 'kind' => AlarmKind::Overload,
            'severity' => Severity::Critical, 'message' => 'Load moment 104% (3.6 t at 50 m)', 'raised_at' => now(),
        ]);

        $events = $this->ask(self::MANAGER, 'Explain this overload alarm and what we should do', ['alarm_id' => $alarm->id]);
        $names = array_column(array_column(array_filter($events, fn ($e) => $e['event'] === 'tool_call'), 'data'), 'name');

        $this->assertSame(['get_crane_status', 'list_alarms', 'search_docs'], $names);
        $answer = implode("\n", array_column(array_column(array_filter($events, fn ($e) => $e['event'] === 'text'), 'data'), 'text'));
        $this->assertMatchesRegularExpression('/\[D\d+§\d+\]/', $answer);
        $this->assertSame('done', end($events)['event']);
        $this->assertSame(2, AiUsage::query()->where('feature', 'assistant')->count(), 'one ledger row per LLM call');
    }

    public function test_cannot_focus_a_conversation_on_another_tenants_alarm(): void
    {
        $alarm = Alarm::query()->create([
            'crane_id' => 'c-101', 'tenant_id' => 't-budinvest', 'kind' => AlarmKind::Wind,
            'severity' => Severity::Warning, 'message' => 'Wind 15 m/s', 'raised_at' => now(),
        ]);

        $this->as(self::VIEWER)->postJson('/api/assistant/conversations', ['alarm_id' => $alarm->id])->assertNotFound();
    }

    public function test_mutating_tools_wait_for_user_confirmation(): void
    {
        $alarm = Alarm::query()->create([
            'crane_id' => 'c-101', 'tenant_id' => 't-budinvest', 'kind' => AlarmKind::Wind,
            'severity' => Severity::Warning, 'message' => 'Wind 15 m/s', 'raised_at' => now(),
        ]);

        $events = $this->ask(self::MANAGER, "Please acknowledge alarm {$alarm->id}");

        $this->assertContains('confirm', array_column($events, 'event'));
        $this->assertNull($alarm->fresh()?->acknowledged_at, 'the model alone never changes state');
    }

    public function test_agent_loop_is_bounded(): void
    {
        // A model that keeps calling tools forever.
        for ($i = 0; $i < 10; $i++) {
            FakeLlmClient::script(FakeLlmClient::toolCall('list_cranes', []));
        }

        $events = $this->ask(self::OPERATOR, 'loop');

        $this->assertCount((int) config('ai.max_tool_iterations'), FakeLlmClient::$requests);
        $this->assertContains('error', array_column($events, 'event'));
    }

    public function test_llm_failure_ends_the_turn_with_an_error_event(): void
    {
        FakeLlmClient::script(fn (LlmRequest $r) => throw new LlmException('The AI service is rate limited, try again shortly.', retryable: true));

        $events = $this->ask(self::OPERATOR, 'hello');

        $this->assertSame('error', $events[0]['event']);
        $this->assertTrue($events[0]['data']['retryable']);
    }

    public function test_daily_budget_is_enforced_per_tenant(): void
    {
        config(['ai.daily_token_budget' => 100]);
        $this->app->forgetInstance(TokenBudget::class);
        Cache::put('ai:budget:t-budinvest:'.now()->toDateString(), 100);

        $id = $this->as(self::MANAGER)->postJson('/api/assistant/conversations')->json('conversation.id');
        $this->as(self::MANAGER)->postJson("/api/assistant/conversations/{$id}/messages", ['message' => 'hi'])->assertStatus(429);

        // Another tenant's bucket is unaffected.
        $this->assertNotEmpty($this->ask(self::VIEWER, 'fleet overview'));
    }

    public function test_history_is_replayed_verbatim_and_append_only(): void
    {
        $id = $this->as(self::OPERATOR)->postJson('/api/assistant/conversations')->json('conversation.id');
        $this->as(self::OPERATOR)->post("/api/assistant/conversations/{$id}/messages", ['message' => 'fleet overview'])->streamedContent();
        $first = FakeLlmClient::$requests[array_key_last(FakeLlmClient::$requests)]->messages;

        $this->as(self::OPERATOR)->post("/api/assistant/conversations/{$id}/messages", ['message' => 'and TC-101?'])->streamedContent();
        $second = FakeLlmClient::$requests[array_key_last(FakeLlmClient::$requests)]->messages;

        // Earlier turns are an exact prefix of later requests (prompt cache + thinking replay stay valid).
        $this->assertEquals(json_encode($first), json_encode(array_slice($second, 0, count($first))));
    }
}
