<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $document_id
 * @property string|null $tenant_id
 * @property int $ordinal
 * @property string $heading
 * @property string $content
 * @property int $token_estimate
 */
class DocumentChunk extends Model
{
    protected $guarded = ['id'];

    // The embedding/tsv columns are written with raw SQL and never loaded into models.
}
