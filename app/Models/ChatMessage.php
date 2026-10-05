<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    use HasFactory;
    use HasUuidKey;

    protected $fillable = ['thread_id', 'role', 'content', 'parts', 'tokens'];

    protected function casts(): array
    {
        return ['parts' => 'array', 'tokens' => 'integer'];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ChatThread::class, 'thread_id');
    }
}
