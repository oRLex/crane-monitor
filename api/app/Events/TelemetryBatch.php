<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** One tick of telemetry for one tenant: to the tenant's channel and the platform channel. */
class TelemetryBatch implements ShouldBroadcastNow
{
    /** @param list<array<string, mixed>> $points */
    public function __construct(public readonly string $tenantId, public readonly array $points) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("tenant.{$this->tenantId}"), new PrivateChannel('platform')];
    }

    public function broadcastAs(): string
    {
        return 'telemetry';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['points' => $this->points];
    }
}
