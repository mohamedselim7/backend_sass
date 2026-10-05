<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupportThread extends Model
{
    use HasFactory;
    use HasUuidKey;
    use SoftDeletes;

    public const OPEN_STATUSES = ['open', 'pending'];

    protected $fillable = [
        'user_id', 'assigned_to', 'subject', 'category', 'status', 'priority',
        'last_message_at', 'unread_for_admin', 'unread_for_user',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'unread_for_admin' => 'integer',
            'unread_for_user' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'thread_id')->orderBy('created_at');
    }

    public function latestMessage(): HasMany
    {
        return $this->messages()->latest();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->hasAnyRole(['admin', 'support'])
            ? $query
            : $query->where('user_id', $user->getKey());
    }
}
