<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string $city
 * @property string $tenant_id
 */
class Site extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'name', 'city', 'tenant_id'];

    /** @return HasMany<Crane, $this> */
    public function cranes(): HasMany
    {
        return $this->hasMany(Crane::class);
    }
}
