<?php

namespace App\Telemetry;

use App\Events\AlarmChanged;
use App\Events\TelemetryBatch;
use App\Models\Alarm;
use App\Models\Crane;
use Illuminate\Support\Carbon;

/**
 * Consumer side of the telemetry bus: evaluates alarm rules, persists alarm changes,
 * keeps the live window and hourly aggregates, and fans out to WebSocket channels.
 *
 * Assumes a single consumer (one RabbitMQ queue, one process), so open alarms are cached
 * in memory instead of being re-queried every tick.
 */
class TelemetryProcessor
{
    /** @var array<string, list<OpenAlarm>> */
    private array $open = [];

    /** @var array<string, string> crane id -> tenant id */
    private array $tenantOf;

    private int $lastFlush = 0;

    public function __construct(
        private readonly TelemetryStore $store,
        private readonly HourlyAggregator $aggregator,
        private readonly bool $broadcast = true,
    ) {
        $this->tenantOf = Crane::query()->pluck('tenant_id', 'id')->all();
        foreach (Alarm::query()->open()->get() as $alarm) {
            $this->open[$alarm->crane_id][] = new OpenAlarm($alarm->id, $alarm->kind, $alarm->severity);
        }
    }

    /** @param list<TelemetryPoint> $points */
    public function process(array $points): void
    {
        $changed = [];
        foreach ($points as $p) {
            if (! isset($this->tenantOf[$p->craneId])) {
                continue; // unknown crane: drop rather than leak into a tenant-less channel
            }
            $changed = [...$changed, ...$this->reconcile($p)];
            $this->aggregator->add($p);
        }

        $this->store->append($points);

        if ($this->broadcast) {
            $byTenant = [];
            foreach ($points as $p) {
                if (isset($this->tenantOf[$p->craneId])) {
                    $byTenant[$this->tenantOf[$p->craneId]][] = $p->toArray();
                }
            }
            foreach ($byTenant as $tenantId => $batch) {
                broadcast(new TelemetryBatch($tenantId, $batch));
            }
            foreach ($changed as $alarm) {
                broadcast(new AlarmChanged($alarm));
            }
        }

        $now = $points === [] ? 0 : $points[0]->ts;
        if ($now - $this->lastFlush >= 60_000) {
            $this->aggregator->flush();
            $this->lastFlush = $now;
        }
    }

    public function flush(): void
    {
        $this->aggregator->flush();
    }

    /** @return list<Alarm> */
    private function reconcile(TelemetryPoint $p): array
    {
        $open = $this->open[$p->craneId] ?? [];
        ['clear' => $clear, 'raise' => $raise] = AlarmEngine::reconcile($open, AlarmEngine::evaluate($p));
        if ($clear === [] && $raise === []) {
            return [];
        }

        $at = Carbon::createFromTimestampMs($p->ts);
        $changed = [];
        $clearIds = array_map(fn (OpenAlarm $a) => $a->id, $clear);
        if ($clearIds !== []) {
            Alarm::query()->whereIn('id', $clearIds)->update(['cleared_at' => $at]);
            $changed = [...$changed, ...Alarm::query()->whereIn('id', $clearIds)->get()->all()];
        }
        $open = array_values(array_filter($open, fn (OpenAlarm $a) => ! in_array($a->id, $clearIds, true)));

        foreach ($raise as $cond) {
            $alarm = Alarm::query()->create([
                'crane_id' => $p->craneId,
                'tenant_id' => $this->tenantOf[$p->craneId],
                'kind' => $cond->kind,
                'severity' => $cond->severity,
                'message' => $cond->message,
                'raised_at' => $at,
            ]);
            $open[] = new OpenAlarm($alarm->id, $alarm->kind, $alarm->severity);
            $changed[] = $alarm;
        }
        $this->open[$p->craneId] = $open;

        return $changed;
    }
}
