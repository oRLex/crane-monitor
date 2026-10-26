<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $conversation_id
 * @property string $role
 * @property list<array<string, mixed>> $content API content blocks, replayed verbatim
 * @property Carbon $created_at
 */
class AiMessage extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['content' => 'array'];
    }
}
