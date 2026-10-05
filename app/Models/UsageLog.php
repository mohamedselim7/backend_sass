<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsageLog extends Model
{
    use HasFactory;
    use HasUuidKey;

    protected $fillable = [
        'user_id', 'brand_id', 'brand_name', 'kind', 'feature', 'action', 'provider', 'model',
        'tokens', 'prompt_tokens', 'completion_tokens', 'images', 'cost', 'ref_id', 'status',
        'error', 'details',
    ];

    protected function casts(): array
    {
        return ['details' => 'array', 'cost' => 'float'];
    }
}
