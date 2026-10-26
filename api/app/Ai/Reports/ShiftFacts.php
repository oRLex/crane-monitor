<?php

namespace App\Ai\Reports;

use App\Models\Alarm;
use App\Models\Crane;
use App\Models\TelemetryHourly;
use Illuminate\Support\Carbon;

/**
 * Everything numeric in a report is computed here, in SQL/PHP — not by the model. The LLM
 * only turns these facts into prose and judgement, which keeps reports cheap, checkable
 * and cacheable (same facts -> same input hash -> reuse).
 */
class ShiftFacts
{
    /** @return array<string, mixed> */
    public function collect(Crane $crane, Carbon $from, Carbon $to): array
    {
        $hours = TelemetryHourly::query()->where('crane_id', $crane->id)
            ->where('hour', '>=', $from->copy()->startOfHour())->where('hour', '<', $to)->orderBy('hour')->get();
        $alarms = Alarm::query()->where('crane_id', $crane->id)->whereBetween('raised_at', [$from, $to])->orderBy('raised_at')->get();
        $samples = (int) $hours->sum('samples');

        $acked = $alarms->filter(fn (Alarm $a) => $a->acknowledged_at !== null);
        $notable = $alarms->filter(fn (Alarm $a) => $a->severity->value === 'critical' || $a->kind->value === 'overload')
            ->sortByDesc(fn (Alarm $a) => [$a->severity->rank(), $a->raised_at->getTimestamp()])->take(8)
            ->sortBy(fn (Alarm $a) => $a->raised_at->getTimestamp())->values();

        return [
            'crane' => ['id' => $crane->id, 'name' => $crane->name, 'model' => $crane->model, 'rated_moment_tm' => $crane->rated_moment_tm, 'max_load_t' => $crane->max_load_t],
            'period' => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String(), 'hours' => (int) round($from->diffInHours($to))],
            'totals' => [
                'lifts' => (int) $hours->sum('lifts'),
                'operating_min' => intdiv((int) $hours->sum('operating_s'), 60),
                'offline_min' => intdiv((int) $hours->sum('offline_s'), 60),
                'peak_load_t' => round((float) $hours->max('max_load_t'), 2),
                'peak_load_moment_pct' => round((float) $hours->max('max_moment_pct'), 1),
                'peak_wind_ms' => round((float) $hours->max('max_wind_ms'), 1),
                'avg_wind_ms' => $samples > 0 ? round((float) $hours->sum('wind_sum') / $samples, 1) : null,
                'busiest_hour' => $hours->sortByDesc('lifts')->first()?->hour->toIso8601String(),
            ],
            'alarms' => [
                'total' => $alarms->count(),
                'critical' => $alarms->filter(fn (Alarm $a) => $a->severity->value === 'critical')->count(),
                'by_kind' => $alarms->countBy(fn (Alarm $a) => $a->kind->value)->all(),
                'acknowledged' => $acked->count(),
                'median_ack_s' => $acked->isEmpty() ? null : (int) $acked->map(fn (Alarm $a) => $a->raised_at->diffInSeconds($a->acknowledged_at))->median(),
                'still_open' => $alarms->filter(fn (Alarm $a) => $a->cleared_at === null)->count(),
            ],
            'notable_alarms' => $notable->map(fn (Alarm $a) => [
                'raised_at' => $a->raised_at->toIso8601String(),
                'kind' => $a->kind->value,
                'severity' => $a->severity->value,
                'message' => $a->message,
                'duration_s' => $a->cleared_at ? (int) $a->raised_at->diffInSeconds($a->cleared_at) : null,
            ])->all(),
        ];
    }
}
