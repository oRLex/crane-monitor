<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 */
class Tenant extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'name'];

    /** @return HasMany<Crane, $this> */
    public function cranes(): HasMany
    {
        return $this->hasMany(Crane::class);
    }
}
