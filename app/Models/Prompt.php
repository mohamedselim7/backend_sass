<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prompt extends Model
{
    use HasFactory;
    use HasUuidKey;
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'title', 'body', 'category', 'tags', 'is_shared',
        'section', 'prompt', 'result', 'notes', 'brand_id', 'brand_name', 'is_favorite',
    ];

    protected function casts(): array
    {
        return ['tags' => 'array', 'is_shared' => 'boolean', 'is_favorite' => 'boolean'];
    }

    /** Own prompts plus the shared library. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('user_id', $user->getKey())->orWhere('is_shared', true);
        });
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('title', 'like', $like)
                ->orWhere('prompt', 'like', $like)
                ->orWhere('body', 'like', $like);
        });
    }
}
