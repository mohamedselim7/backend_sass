<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentGeneration extends Model
{
    use HasFactory;
    use HasUuidKey;
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'brand_id', 'brand_name', 'business_type', 'business_brief',
        'monthly_brief', 'options', 'status', 'provider', 'model', 'write_mode', 'prompt_version',
    ];

    protected function casts(): array
    {
        return ['options' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(ContentPlan::class, 'generation_id')->orderBy('plan_index');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->where('user_id', $user->getKey());
    }
}
