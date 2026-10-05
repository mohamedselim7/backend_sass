<?php

namespace App\Models;

use App\Enums\PostStatus;
use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentPost extends Model
{
    use HasFactory;
    use HasUuidKey;
    use SoftDeletes;

    protected $fillable = [
        'plan_id', 'brand_id', 'user_id', 'post_number', 'content_type', 'funnel_stage',
        'headline', 'content', 'cta', 'design_idea', 'platform', 'status', 'reject_reason',
        'source', 'hashtags', 'media', 'notes', 'suggested_platforms',
        'language_id', 'dialect_id', 'tone_id', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'hashtags' => 'array',
            'media' => 'array',
            'suggested_platforms' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ContentPlan::class, 'plan_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function designs(): HasMany
    {
        return $this->hasMany(Design::class, 'post_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->where('user_id', $user->getKey());
    }

    public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}
}
