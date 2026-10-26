<?php

namespace App\Telemetry;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/** Live window per crane: latest point + short history, kept in Redis (any cache store in tests). */
class TelemetryStore
{
    private Repository $cache;

    public function __construct(private readonly int $historyPoints = 120, ?string $store = null)
    {
        $this->cache = Cache::store($store);
    }

    /** @param list<TelemetryPoint> $points */
    public function append(array $points): void
    {
        $latest = $this->cache->get('telemetry:latest', []);
        foreach ($points as $p) {
            $latest[$p->craneId] = $p->toArray();
            $history = $this->cache->get("telemetry:history:{$p->craneId}", []);
            $history[] = $p->toArray();
            $this->cache->forever("telemetry:history:{$p->craneId}", array_slice($history, -$this->historyPoints));
        }
        $this->cache->forever('telemetry:latest', $latest);
    }

    public function latest(string $craneId): ?TelemetryPoint
    {
        $row = $this->cache->get('telemetry:latest', [])[$craneId] ?? null;

        return $row === null ? null : TelemetryPoint::fromArray($row);
    }

    /** @return list<TelemetryPoint> */
    public function history(string $craneId, ?int $limit = null): array
    {
        $rows = $this->cache->get("telemetry:history:{$craneId}", []);
        if ($limit !== null) {
            $rows = array_slice($rows, -$limit);
        }

        return array_values(array_map(TelemetryPoint::fromArray(...), $rows));
    }
}
