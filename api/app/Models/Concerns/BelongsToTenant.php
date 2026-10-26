<?php

namespace App\Models\Concerns;

use App\Auth\TenantContext;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant isolation in one place: every query on a model using this trait is filtered
 * to the current principal's tenant. Route model binding therefore 404s for other
 * tenants' records, so ids don't leak between customers.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            $tenant = app(TenantContext::class)->restrictedTenant();
            if ($tenant !== null) {
                $query->where($query->qualifyColumn('tenant_id'), $tenant);
            }
        });
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
