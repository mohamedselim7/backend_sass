<?php

namespace App\Models;

use App\Enums\ScheduleStatus;
use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledPost extends Model
{
    use HasFactory;
    use HasUuidKey;

    protected $fillable = [
        'user_id', 'brand_id', 'post_id', 'platform', 'social_account_id', 'caption',
        'media', 'hashtags', 'scheduled_at', 'timezone', 'status', 'attempts',
        'published_at', 'external_post_id', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'media' => 'array',
            'hashtags' => 'array',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'status' => ScheduleStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(ContentPost::class, 'post_id');
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->where('user_id', $user->getKey());
    }

    /** Due items the publisher command should pick up. */
    public function scopeDue(Builder $query): Builder
    {
        return $query->where('status', ScheduleStatus::Scheduled->value)
            ->where('scheduled_at', '<=', now());
    }
}
