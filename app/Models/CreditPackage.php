<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One-time credit bundle, fully managed from the Admin (no hardcoded prices). */
class CreditPackage extends Model
{
    use HasFactory;
    use HasUuidKey;

    protected $fillable = [
        'name', 'description', 'price', 'currency', 'credits', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'credits' => 'integer', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
