<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;
    use HasUuidKey;

    protected $fillable = [
        'user_id', 'subscription_id', 'credit_package_id', 'gateway', 'amount', 'currency', 'status',
        'gateway_reference', 'gateway_transaction_id', 'payload', 'paid_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'payload' => 'array', 'paid_at' => 'datetime'];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function creditPackage(): BelongsTo
    {
        return $this->belongsTo(CreditPackage::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
