<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $feature
 * @property string $model
 * @property string|null $tenant_id
 * @property int|null $user_id
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $cache_read_tokens
 * @property int $cache_write_tokens
 * @property string $cost_usd
 * @property int $latency_ms
 * @property string|null $stop_reason
 * @property bool $mock
 * @property Carbon $created_at
 */
class AiUsage extends Model
{
    protected $table = 'ai_usage';

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['mock' => 'boolean'];
    }
}
