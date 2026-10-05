<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditWallet extends Model
{
    use HasFactory;
    use HasUuidKey;

    protected $fillable = ['user_id', 'balance', 'lifetime_granted', 'lifetime_spent', 'last_granted_at'];

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
            'lifetime_granted' => 'integer',
            'lifetime_spent' => 'integer',
            'last_granted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
