<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentPlan extends Model
{
    use HasFactory;
    use HasUuidKey;

    protected $fillable = [
        'generation_id', 'brand_id', 'plan_index', 'name', 'strategy', 'goal',
        'target_audience', 'pillars', 'funnel', 'formats', 'language_id', 'dialect_id', 'tone_id',
    ];

    protected function casts(): array
    {
        return ['pillars' => 'array', 'funnel' => 'array', 'formats' => 'array'];
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(ContentGeneration::class, 'generation_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(ContentPost::class, 'plan_id')->orderBy('post_number');
    }
}
