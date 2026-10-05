<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use HasFactory;
    use HasUuidKey;
    use SoftDeletes;

    protected $fillable = [
        'created_by', 'name', 'industry', 'description', 'target_audience', 'target_market',
        'tone_of_voice', 'content_language', 'dialect', 'platforms', 'logo_url', 'colors',
        'fonts', 'guidelines_url', 'reference_images', 'website', 'social', 'notes', 'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'platforms' => 'array',
            'colors' => 'array',
            'reference_images' => 'array',
            'social' => 'array',
            'is_demo' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function contentPosts(): HasMany
    {
        return $this->hasMany(ContentPost::class);
    }

    /** Non-admins only ever see their own brands. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->where('created_by', $user->getKey());
    }
}
