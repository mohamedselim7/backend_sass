<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OauthState extends Model
{
    use HasFactory;
    use HasUuidKey;

    protected $fillable = ['user_id', 'platform', 'state', 'redirect_uri', 'payload', 'expires_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'expires_at' => 'datetime'];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
