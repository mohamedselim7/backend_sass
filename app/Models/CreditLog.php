<?php

namespace App\Models;

use App\Enums\CreditReason;
use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditLog extends Model
{
    use HasFactory;
    use HasUuidKey;

    protected $fillable = [
        'user_id', 'amount', 'balance_after', 'reason', 'feature', 'ref_id', 'idempotency_key', 'details',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_after' => 'integer',
            'reason' => CreditReason::class,
            'details' => 'array',
        ];
    }
}
