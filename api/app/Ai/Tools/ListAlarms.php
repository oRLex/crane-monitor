<?php

namespace App\Ai\Tools;

use App\Domain\Scope;
use App\Models\Alarm;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class ListAlarms extends Tool
{
    public function __construct(private readonly FleetTools $fleet) {}

    public function name(): string
    {
        return 'list_alarms';
    }

    public function description(): string
    {
        return 'List alarms (wind, overload, offline), newest first. Filter by crane, open-only, and time window. Returns at most 30 alarms plus counts by kind and severity.';
    }

    public function scope(): Scope
    {
        return Scope::AlarmsRead;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'crane_id' => $schema->string()->description('Only this crane'),
            'open_only' => $schema->boolean()->description('Only alarms whose condition is still present'),
            'hours' => $schema->integer()->description('Look back this many hours, 1-72 (default 12)')->min(1)->max(72),
        ];
    }

    public function rules(): array
    {
        return [
            'crane_id' => ['sometimes', 'string', 'max:40'],
            'open_only' => ['sometimes', 'boolean'],
            'hours' => ['sometimes', 'integer', 'between:1,72'],
        ];
    }

    public function handle(array $input, User $user): array
    {
        $query = Alarm::query()
            ->when($input['crane_id'] ?? null, fn ($q, $id) => $q->where('crane_id', $id))
            ->when($input['open_only'] ?? false, fn ($q) => $q->open())
            ->where('raised_at', '>=', now()->subHours($input['hours'] ?? 12));

        $counts = (clone $query)->selectRaw('kind, severity, count(*) as n')->groupBy('kind', 'severity')->get()
            ->map(fn ($r) => ['kind' => $r->getRawOriginal('kind'), 'severity' => $r->getRawOriginal('severity'), 'count' => (int) $r->getAttribute('n')])->all();

        return [
            'counts' => $counts,
            'alarms' => $query->orderByDesc('raised_at')->limit(30)->get()->map(fn (Alarm $a) => $this->fleet->alarm($a))->all(),
        ];
    }
}
