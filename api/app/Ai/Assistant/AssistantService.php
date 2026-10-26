<?php

namespace App\Ai\Assistant;

use App\Ai\Llm\LlmClient;
use App\Ai\Llm\LlmException;
use App\Ai\Llm\LlmRequest;
use App\Ai\TokenBudget;
use App\Ai\Tools\ToolRegistry;
use App\Ai\UsageRecorder;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use Generator;
use Illuminate\Support\Str;

/**
 * Agent loop for the in-app assistant: model -> tools -> model, at most N iterations.
 *
 * Reliability rules:
 * - history is append-only (no trimming or rewriting), so prompt caching and replayed
 *   thinking blocks stay valid; long conversations are capped instead
 * - tool failures go back to the model as error results; LLM failures end the turn with a
 *   user-facing error, and what was already done is persisted
 * - state-changing tools are never executed from a model decision alone: the user gets a
 *   confirmation button (human in the loop)
 */
class AssistantService
{
    public const MAX_MESSAGES = 60;

    public function __construct(
        private readonly LlmClient $llm,
        private readonly ToolRegistry $tools,
        private readonly UsageRecorder $usage,
        private readonly TokenBudget $budget,
    ) {}

    /** @return Generator<int, array{event: string, data: array<string, mixed>}> */
    public function reply(AiConversation $conversation, User $user, string $text): Generator
    {
        if ($conversation->messages()->count() >= self::MAX_MESSAGES) {
            yield $this->event('error', ['message' => 'This conversation is long; please start a new one so answers stay fast and cheap.']);

            return;
        }
        $this->budget->ensureAvailable($user);

        $this->append($conversation, 'user', [
            ['type' => 'text', 'text' => $this->contextNote($conversation)],
            ['type' => 'text', 'text' => $text],
        ]);

        $tools = $this->tools->for($user);
        $totals = ['input' => 0, 'output' => 0, 'cache_read' => 0, 'tool_calls' => 0];
        $max = (int) config('ai.max_tool_iterations');

        for ($i = 0; $i < $max; $i++) {
            $started = microtime(true);
            try {
                $response = $this->llm->complete(new LlmRequest(
                    feature: 'assistant',
                    system: Prompts::assistant(),
                    messages: $this->history($conversation),
                    tools: $tools,
                ));
            } catch (LlmException $e) {
                yield $this->event('error', ['message' => $e->getMessage(), 'retryable' => $e->retryable]);

                return;
            }
            $this->usage->record('assistant', $response, $user, (int) ((microtime(true) - $started) * 1000));
            $totals['input'] += $response->inputTokens;
            $totals['output'] += $response->outputTokens;
            $totals['cache_read'] += $response->cacheReadTokens;

            $this->append($conversation, 'assistant', $response->content);
            foreach ($response->content as $block) {
                if (($block['type'] ?? null) === 'text' && trim((string) $block['text']) !== '') {
                    yield $this->event('text', ['text' => $block['text']]);
                }
            }

            if ($response->stopReason === 'refusal') {
                yield $this->event('error', ['message' => 'The model declined this request.']);

                break;
            }
            if ($response->stopReason !== 'tool_use') {
                break;
            }

            $results = [];
            foreach ($response->toolUses() as $use) {
                $totals['tool_calls']++;
                $input = (array) $use['input'];
                yield $this->event('tool_call', ['id' => $use['id'], 'name' => $use['name'], 'input' => $input]);

                $tool = $this->tools->get($use['name']);
                if ($tool !== null && $tool->mutating() && $user->hasScope($tool->scope())) {
                    yield $this->event('confirm', ['id' => $use['id'], 'name' => $use['name'], 'input' => $input]);
                    $content = json_encode(['status' => 'awaiting_user_confirmation', 'note' => 'A confirmation button was shown to the user. Do not claim the action is done.']);
                    $ok = true;
                } else {
                    $result = $this->tools->call($use['name'], $input, $user);
                    $content = $result->toJson();
                    $ok = $result->ok;
                    yield $this->event('tool_result', ['id' => $use['id'], 'name' => $use['name'], 'ok' => $ok, 'summary' => ToolSummary::of($use['name'], $result)]);
                }
                $results[] = ['type' => 'tool_result', 'tool_use_id' => $use['id'], 'content' => (string) $content] + ($ok ? [] : ['is_error' => true]);
            }
            // All results of one assistant turn go back in a single user message.
            $this->append($conversation, 'user', $results);

            if ($i === $max - 1) {
                yield $this->event('error', ['message' => 'Stopped after too many tool calls. Try a more specific question.']);
            }
        }

        $conversation->touch();
        yield $this->event('done', ['usage' => $totals, 'mock' => $this->llm->isMock(), 'budget_left' => max(0, $this->budget->limit() - $this->budget->used($user))]);
    }

    /** @return list<array{role: string, content: list<array<string, mixed>>}> */
    private function history(AiConversation $conversation): array
    {
        return array_values($conversation->messages()->get()->map(fn (AiMessage $m) => ['role' => $m->role, 'content' => $m->content])->all());
    }

    /** @param list<array<string, mixed>> $content */
    private function append(AiConversation $conversation, string $role, array $content): void
    {
        $conversation->messages()->create(['role' => $role, 'content' => $content]);
    }

    /**
     * Volatile context goes into the user turn (never the system prompt) to keep the
     * cached prefix stable. It is marked as app-provided so the model doesn't treat it as
     * the user's words.
     */
    private function contextNote(AiConversation $conversation): string
    {
        $focus = array_filter([
            $conversation->crane_id ? "viewing crane {$conversation->crane_id}" : null,
            $conversation->alarm_id ? "asking about alarm {$conversation->alarm_id}" : null,
        ]);

        return '<app_context>Current time: '.now()->toIso8601String().'.'
            .($focus === [] ? '' : ' The user is '.implode(', ', $focus).'.')
            .'</app_context>';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{event: string, data: array<string, mixed>}
     */
    private function event(string $event, array $data): array
    {
        return ['event' => $event, 'data' => $data];
    }

    public static function titleFrom(string $text): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', $text) ?? ''), 60);
    }
}
