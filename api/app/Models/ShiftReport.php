<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $crane_id
 * @property int $requested_by
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property string $status queued | running | ready | failed
 * @property string|null $input_hash
 * @property array<string, mixed>|null $facts
 * @property array<string, mixed>|null $report
 * @property string|null $error
 * @property string|null $model
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Crane $crane
 */
class ShiftReport extends Model
{
    use BelongsToTenant, HasUlids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['period_start' => 'datetime', 'period_end' => 'datetime', 'facts' => 'array', 'report' => 'array'];
    }

    /** @return BelongsTo<Crane, $this> */
    public function crane(): BelongsTo
    {
        return $this->belongsTo(Crane::class);
    }

    /** @return array<string, mixed> */
    public function toWire(): array
    {
        return [
            'id' => $this->id,
            'craneId' => $this->crane_id,
            'craneName' => $this->crane->name,
            'periodStart' => $this->period_start->toIso8601String(),
            'periodEnd' => $this->period_end->toIso8601String(),
            'status' => $this->status,
            'report' => $this->report,
            'facts' => $this->facts,
            'error' => $this->error,
            'model' => $this->model,
            'createdAt' => $this->created_at->toIso8601String(),
        ];
    }
}
