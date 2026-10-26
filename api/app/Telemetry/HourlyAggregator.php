<?php

namespace App\Telemetry;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Accumulates per-crane hourly stats in memory and flushes deltas with an additive upsert,
 * so a restarted consumer (or the seeder replaying history) never double counts.
 */
class HourlyAggregator
{
    /** @var array<string, array{crane_id: string, hour: string, max_load_t: float, max_moment_pct: float, max_wind_ms: float, wind_sum: float, samples: int, operating_s: int, offline_s: int, lifts: int}> */
    private array $buckets = [];

    /** @var array<string, bool> */
    private array $lifting = [];

    public function __construct(private readonly int $tickMs) {}

    public function add(TelemetryPoint $p): void
    {
        $hour = Carbon::createFromTimestampMs($p->ts)->startOfHour()->toDateTimeString();
        $key = "{$p->craneId}|{$hour}";
        $b = $this->buckets[$key] ??= [
            'crane_id' => $p->craneId, 'hour' => $hour, 'max_load_t' => 0.0, 'max_moment_pct' => 0.0,
            'max_wind_ms' => 0.0, 'wind_sum' => 0.0, 'samples' => 0, 'operating_s' => 0, 'offline_s' => 0, 'lifts' => 0,
        ];
        $seconds = intdiv($this->tickMs, 1000) ?: 1;
        $b['samples']++;
        $b['wind_sum'] += $p->windMs;
        $b['max_wind_ms'] = max($b['max_wind_ms'], $p->windMs);
        if (! $p->online) {
            $b['offline_s'] += $seconds;
        } else {
            $b['max_load_t'] = max($b['max_load_t'], $p->loadT);
            $b['max_moment_pct'] = max($b['max_moment_pct'], $p->loadMomentPct);
            if ($p->loadT > 0.2) {
                $b['operating_s'] += $seconds;
            }
            $lifting = $p->loadT > 0.5;
            if ($lifting && ! ($this->lifting[$p->craneId] ?? false)) {
                $b['lifts']++;
            }
            $this->lifting[$p->craneId] = $lifting;
        }
        $this->buckets[$key] = $b;
    }

    public function flush(): void
    {
        if ($this->buckets === []) {
            return;
        }
        DB::table('telemetry_hourly')->upsert(array_values($this->buckets), ['crane_id', 'hour'], [
            'max_load_t' => DB::raw('GREATEST(telemetry_hourly.max_load_t, excluded.max_load_t)'),
            'max_moment_pct' => DB::raw('GREATEST(telemetry_hourly.max_moment_pct, excluded.max_moment_pct)'),
            'max_wind_ms' => DB::raw('GREATEST(telemetry_hourly.max_wind_ms, excluded.max_wind_ms)'),
            'wind_sum' => DB::raw('telemetry_hourly.wind_sum + excluded.wind_sum'),
            'samples' => DB::raw('telemetry_hourly.samples + excluded.samples'),
            'operating_s' => DB::raw('telemetry_hourly.operating_s + excluded.operating_s'),
            'offline_s' => DB::raw('telemetry_hourly.offline_s + excluded.offline_s'),
            'lifts' => DB::raw('telemetry_hourly.lifts + excluded.lifts'),
        ]);
        $this->buckets = [];
    }
}
