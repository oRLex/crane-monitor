<?php

namespace App\Models;

use App\Domain\AlarmKind;
use App\Domain\Severity;
use App\Models\Concerns\BelongsToTenant;
use App\Support\EpochMs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $crane_id
 * @property string $tenant_id
 * @property AlarmKind $kind
 * @property Severity $severity
 * @property string $message
 * @property Carbon $raised_at
 * @property Carbon|null $cleared_at
 * @property Carbon|null $acknowledged_at
 * @property string|null $acknowledged_by
 * @property-read Crane $crane
 */
class Alarm extends Model
{
    use BelongsToTenant, HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'id', 'crane_id', 'tenant_id', 'kind', 'severity', 'message',
        'raised_at', 'cleared_at', 'acknowledged_at', 'acknowledged_by',
    ];

    protected function casts(): array
    {
        return [
            'kind' => AlarmKind::class,
            'severity' => Severity::class,
            'raised_at' => 'datetime',
            'cleared_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Crane, $this> */
    public function crane(): BelongsTo
    {
        return $this->belongsTo(Crane::class);
    }

    /** @param Builder<Alarm> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('cleared_at');
    }

    /**
     * Wire format shared by REST, broadcasts and AI tools (epoch ms, like the telemetry).
     *
     * @return array{id: string, craneId: string, kind: string, severity: string, message: string, raisedAt: float, clearedAt: float|null, acknowledgedAt: float|null, acknowledgedBy: string|null}
     */
    public function toWire(): array
    {
        return [
            'id' => $this->id,
            'craneId' => $this->crane_id,
            'kind' => $this->kind->value,
            'severity' => $this->severity->value,
            'message' => $this->message,
            'raisedAt' => EpochMs::of($this->raised_at),
            'clearedAt' => $this->cleared_at ? EpochMs::of($this->cleared_at) : null,
            'acknowledgedAt' => $this->acknowledged_at ? EpochMs::of($this->acknowledged_at) : null,
            'acknowledgedBy' => $this->acknowledged_by,
        ];
    }
}
