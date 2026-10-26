<?php

namespace App\Events;

use App\Models\ShiftReport;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class ReportReady implements ShouldBroadcastNow
{
    public function __construct(public readonly ShiftReport $report) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("tenant.{$this->report->tenant_id}"), new PrivateChannel('platform')];
    }

    public function broadcastAs(): string
    {
        return 'report';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['id' => $this->report->id, 'status' => $this->report->status, 'craneId' => $this->report->crane_id];
    }
}
