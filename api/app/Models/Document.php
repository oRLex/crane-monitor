<?php

namespace App\Models;

use App\Auth\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $tenant_id null = platform-wide library
 * @property string $title
 * @property string|null $source
 * @property string $content
 * @property string $content_hash
 * @property string $status
 * @property string|null $error
 * @property int $chunk_count
 * @property string|null $embedding_model
 * @property int|null $uploaded_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Document extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        // Like BelongsToTenant, but platform documents (tenant_id null) are visible to everyone.
        static::addGlobalScope('tenant', function (Builder $query) {
            $tenant = app(TenantContext::class)->restrictedTenant();
            if ($tenant !== null) {
                $query->where(fn (Builder $q) => $q->whereNull('documents.tenant_id')->orWhere('documents.tenant_id', $tenant));
            }
        });
    }

    /** @return HasMany<DocumentChunk, $this> */
    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }
}
