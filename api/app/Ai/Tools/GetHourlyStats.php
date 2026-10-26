<?php

namespace App\Ai\Tools;

use App\Domain\Scope;
use App\Models\Crane;
use App\Models\TelemetryHourly;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class GetHourlyStats extends Tool
{
    public function name(): string
    {
        return 'get_hourly_stats';
    }

    public function description(): string
    {
        return 'Hourly aggregates for one crane over the last N hours (max 24): peak load, peak load moment, peak and average wind, operating and offline minutes, number of lifts.';
    }

    public function scope(): Scope
    {
        return Scope::TelemetryRead;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'crane_id' => $schema->string()->description('Crane id')->required(),
            'hours' => $schema->integer()->description('How many hours back, 1-24 (default 12)')->min(1)->max(24),
        ];
    }

    public function rules(): array
    {
        return ['crane_id' => ['required', 'string', 'max:40'], 'hours' => ['sometimes', 'integer', 'between:1,24']];
    }

    public function handle(array $input, User $user): array
    {
        $crane = Crane::query()->findOrFail((string) $input['crane_id']); // tenant check
        $rows = TelemetryHourly::query()->where('crane_id', $crane->id)
            ->where('hour', '>=', now()->subHours($input['hours'] ?? 12)->startOfHour())
            ->orderBy('hour')->get();

        return ['crane_id' => $crane->id, 'hours' => $rows->map(fn (TelemetryHourly $h) => [
            'hour' => $h->hour->toIso8601String(),
            'peak_load_t' => round($h->max_load_t, 2),
            'peak_load_moment_pct' => round($h->max_moment_pct, 1),
            'peak_wind_ms' => round($h->max_wind_ms, 1),
            'avg_wind_ms' => $h->samples > 0 ? round($h->wind_sum / $h->samples, 1) : null,
            'operating_min' => intdiv($h->operating_s, 60),
            'offline_min' => intdiv($h->offline_s, 60),
            'lifts' => $h->lifts,
        ])->all()];
    }
}
