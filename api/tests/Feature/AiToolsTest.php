<?php

namespace Tests\Feature;

use App\Ai\Tools\ToolRegistry;
use App\Domain\AlarmKind;
use App\Domain\Severity;
use App\Models\Alarm;
use Tests\TestCase;

class AiToolsTest extends TestCase
{
    public function test_tools_are_advertised_by_scope(): void
    {
        $registry = app(ToolRegistry::class);
        $names = fn (string $email) => array_map(fn ($t) => $t->name(), $registry->for($this->user($email)));

        $this->assertContains('acknowledge_alarm', $names(self::MANAGER));
        $this->assertNotContains('acknowledge_alarm', $names(self::VIEWER));
    }

    public function test_tools_run_in_the_callers_tenant(): void
    {
        $registry = app(ToolRegistry::class);
        $viewer = $this->user(self::VIEWER);

        $cranes = $registry->call('list_cranes', [], $viewer);
        $this->assertTrue($cranes->ok);
        $this->assertSame(['c-201', 'c-202'], array_column($cranes->data['cranes'], 'id'));

        // Another tenant's crane looks exactly like a missing one.
        $foreign = $registry->call('get_crane_status', ['crane_id' => 'c-101'], $viewer);
        $this->assertFalse($foreign->ok);
        $this->assertSame('Not found', $foreign->error);
    }

    public function test_scope_is_enforced_even_if_a_model_calls_a_hidden_tool(): void
    {
        $alarm = Alarm::query()->create([
            'crane_id' => 'c-201', 'tenant_id' => 't-skyline', 'kind' => AlarmKind::Wind,
            'severity' => Severity::Warning, 'message' => 'Wind 15 m/s', 'raised_at' => now(),
        ]);

        $result = app(ToolRegistry::class)->call('acknowledge_alarm', ['alarm_id' => $alarm->id], $this->user(self::VIEWER));

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('alarms:ack', (string) $result->error);
        $this->assertNull($alarm->fresh()?->acknowledged_at);
    }

    public function test_invalid_input_is_returned_to_the_model_as_an_error(): void
    {
        $result = app(ToolRegistry::class)->call('get_hourly_stats', ['crane_id' => 'c-201', 'hours' => 500], $this->user(self::VIEWER));

        $this->assertFalse($result->ok);
        $this->assertStringStartsWith('Invalid input', (string) $result->error);
    }

    public function test_tool_schemas_are_strict_json_schema(): void
    {
        foreach (app(ToolRegistry::class)->all() as $tool) {
            $schema = $tool->definition()['input_schema'];
            $this->assertSame('object', $schema['type'], $tool->name());
            $this->assertFalse($schema['additionalProperties'], $tool->name());
        }
    }
}
