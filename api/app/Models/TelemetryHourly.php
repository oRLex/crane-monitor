<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $crane_id
 * @property Carbon $hour
 * @property float $max_load_t
 * @property float $max_moment_pct
 * @property float $max_wind_ms
 * @property float $wind_sum
 * @property int $samples
 * @property int $operating_s
 * @property int $offline_s
 * @property int $lifts
 */
class TelemetryHourly extends Model
{
    protected $table = 'telemetry_hourly';

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['hour' => 'datetime'];
    }
}
