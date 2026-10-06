<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;
    use HasUuidKey;

    protected $fillable = [
        'code', 'name', 'description', 'price', 'currency', 'interval',
        'monthly_credits', 'features', 'is_active', 'sort_order', 'ai_model',
        'ai_queue', 'queue_priority',
    ];

    /** @return array<int,string> */
    public static function aiModels(): array
    {
        return config('ai.plan_models', ['openai', 'gemini', 'nvidia']);
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'features' => 'array',
            'is_active' => 'boolean',
            'queue_priority' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
