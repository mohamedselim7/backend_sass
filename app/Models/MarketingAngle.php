<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingAngle extends Model
{
    use HasFactory;
    use HasUuidKey;
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'brand_id', 'expertise_id', 'title', 'body', 'category', 'tags', 'understanding', 'status',
        'details', 'source', 'edited_by_user', 'usage_count', 'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'understanding' => 'array',
            'details' => 'array',
            'edited_by_user' => 'boolean',
            'usage_count' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->where('user_id', $user->getKey());
    }
}
