<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    use HasFactory;
    use HasUuidKey;

    protected $fillable = [
        'thread_id', 'sender_id', 'author_type', 'body', 'attachments', 'read_at',
    ];

    protected function casts(): array
    {
        return ['attachments' => 'array', 'read_at' => 'datetime'];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(SupportThread::class, 'thread_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
