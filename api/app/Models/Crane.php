<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string $model
 * @property string $serial_number
 * @property float $max_load_t
 * @property float $jib_length_m
 * @property float $rated_moment_tm Rated load moment, tonne-metres.
 * @property string|null $stream_url
 * @property string $site_id
 * @property string $tenant_id
 * @property-read Site $site
 */
class Crane extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'name', 'model', 'serial_number', 'max_load_t', 'jib_length_m',
        'rated_moment_tm', 'stream_url', 'site_id', 'tenant_id',
    ];

    protected function casts(): array
    {
        return ['max_load_t' => 'float', 'jib_length_m' => 'float', 'rated_moment_tm' => 'float'];
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return HasMany<Alarm, $this> */
    public function alarms(): HasMany
    {
        return $this->hasMany(Alarm::class);
    }
}
