<?php

namespace App\Ai\Tools;

use App\Domain\Scope;
use App\Models\Alarm;
use App\Models\Crane;
use App\Models\User;
use App\Telemetry\TelemetryStore;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class GetCraneStatus extends Tool
{
    public function __construct(private readonly FleetTools $fleet, private readonly TelemetryStore $store) {}

    public function name(): string
    {
        return 'get_crane_status';
    }

    public function description(): string
    {
        return 'Detailed state of one crane: specification and alarm limits, latest reading, a summary of the last ~2 minutes of telemetry (peaks and averages) and its open alarms.';
    }

    public function scope(): Scope
    {
        return Scope::TelemetryRead;
    }

    public function schema(JsonSchema $schema): array
    {
        return ['crane_id' => $schema->string()->description('Crane id, e.g. "c-101"')->required()];
    }

    public function rules(): array
    {
        return ['crane_id' => ['required', 'string', 'max:40']];
    }

    public function handle(array $input, User $user): array
    {
        $crane = Crane::query()->with('site')->findOrFail((string) $input['crane_id']);
        $history = $this->store->history($crane->id);
        $online = array_filter($history, fn ($p) => $p->online);
        $recent = $online === [] ? null : [
            'samples' => count($history),
            'offline_samples' => count($history) - count($online),
            'peak_load_moment_pct' => max(array_map(fn ($p) => $p->loadMomentPct, $online)),
            'peak_load_t' => max(array_map(fn ($p) => $p->loadT, $online)),
            'peak_wind_ms' => max(array_map(fn ($p) => $p->windMs, $online)),
            'avg_wind_ms' => round(array_sum(array_map(fn ($p) => $p->windMs, $online)) / count($online), 1),
        ];

        return [
            ...$this->fleet->summary($crane),
            'spec' => $this->fleet->spec($crane),
            'recent' => $recent,
            'open_alarms' => Alarm::query()->where('crane_id', $crane->id)->open()->orderByDesc('raised_at')->get()->map(fn (Alarm $a) => $this->fleet->alarm($a))->all(),
        ];
    }
}
