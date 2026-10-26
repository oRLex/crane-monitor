<?php

namespace App\Events;

use App\Models\Alarm;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class AlarmChanged implements ShouldBroadcastNow
{
    public function __construct(public readonly Alarm $alarm) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("tenant.{$this->alarm->tenant_id}"), new PrivateChannel('platform')];
    }

    public function broadcastAs(): string
    {
        return 'alarm';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['alarm' => $this->alarm->toWire()];
    }
}
