<?php

namespace App\Ai\Tools;

use App\Domain\Scope;
use App\Events\AlarmChanged;
use App\Models\Alarm;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class AcknowledgeAlarm extends Tool
{
    public function name(): string
    {
        return 'acknowledge_alarm';
    }

    public function description(): string
    {
        return 'Acknowledge an alarm on behalf of the user (records who and when). Only after the user explicitly asked for it. Idempotent.';
    }

    public function scope(): Scope
    {
        return Scope::AlarmsAck;
    }

    public function mutating(): bool
    {
        return true;
    }

    public function schema(JsonSchema $schema): array
    {
        return ['alarm_id' => $schema->string()->description('Alarm id')->required()];
    }

    public function rules(): array
    {
        return ['alarm_id' => ['required', 'string', 'max:40']];
    }

    public function handle(array $input, User $user): array
    {
        $alarm = Alarm::query()->findOrFail((string) $input['alarm_id']);
        if ($alarm->acknowledged_at === null) {
            $alarm->update(['acknowledged_at' => now(), 'acknowledged_by' => $user->name]);
            broadcast(new AlarmChanged($alarm));
        }

        return ['acknowledged' => true, 'alarm_id' => $alarm->id, 'acknowledged_by' => $alarm->acknowledged_by];
    }
}
