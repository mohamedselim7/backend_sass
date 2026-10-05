<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use HasFactory;
    use HasUuidKey;

    protected $fillable = [
        'user_id', 'brand_id', 'brand_name', 'actor', 'action', 'entity',
        'feature', 'status', 'cost', 'details',
    ];

    protected function casts(): array
    {
        return ['details' => 'array', 'cost' => 'float'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}