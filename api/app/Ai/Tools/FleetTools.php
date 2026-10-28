<?php

namespace App\Ai\Tools;

use App\Models\Alarm;
use App\Models\Crane;
use App\Telemetry\AlarmEngine;
use App\Telemetry\TelemetryPoint;
use App\Telemetry\TelemetryStore;

/** Shared read helpers so every tool describes cranes the same way. */
class FleetTools
{
    public function __construct(private readonly TelemetryStore $store) {}

    /** @return array<string, mixed> */
    public function summary(Crane $crane): array
    {
        $latest = $this->store->latest($crane->id);
        $openAlarms = Alarm::query()->where('crane_id', $crane->id)->open()->count();

        return [
            'id' => $crane->id,
            'name' => $crane->name,
            'model' => $crane->model,
            'site' => $crane->site->name.', '.$crane->site->city,
            'status' => AlarmEngine::status($latest, $openAlarms > 0),
            'open_alarms' => $openAlarms,
            'latest' => $latest ? $this->point($latest) : null,
        ];
    }

    /** @return array<string, mixed> */
    public function spec(Crane $crane): array
    {
        return [
            'max_load_t' => $crane->max_load_t,
            'jib_length_m' => $crane->jib_length_m,
            'rated_moment_tm' => $crane->rated_moment_tm,
            'serial_number' => $crane->serial_number,
            'limits' => [
                'wind_warning_ms' => AlarmEngine::WIND_WARNING,
                'wind_stop_ms' => AlarmEngine::WIND_CRITICAL,
                'load_moment_warning_pct' => AlarmEngine::MOMENT_WARNING,
                'load_moment_trip_pct' => AlarmEngine::MOMENT_CRITICAL,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function point(TelemetryPoint $p): array
    {
        return [
            'at' => date('c', (int) floor($p->ts / 1000)),
            'online' => $p->online,
            'load_t' => $p->loadT,
            'radius_m' => $p->radiusM,
            'load_moment_pct' => $p->loadMomentPct,
            'wind_ms' => $p->windMs,
            'hook_height_m' => $p->hookHeightM,
        ];
    }

    /** @return array<string, mixed> */
    public function alarm(Alarm $a): array
    {
        return [
            'id' => $a->id,
            'crane_id' => $a->crane_id,
            'kind' => $a->kind->value,
            'severity' => $a->severity->value,
            'message' => $a->message,
            'raised_at' => $a->raised_at->toIso8601String(),
            'cleared_at' => $a->cleared_at?->toIso8601String(),
            'acknowledged_by' => $a->acknowledged_by,
        ];
    }
}
