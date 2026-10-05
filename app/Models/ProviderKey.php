<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProviderKey extends Model
{
    use HasFactory;
    use HasUuidKey;

    protected $fillable = [
        'provider', 'api_key', 'default_model', 'is_active', 'status', 'last_error', 'verified_at',
    ];

    /** Never exposed through the API — admins only ever see a masked hint. */
    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return ['api_key' => 'encrypted', 'is_active' => 'boolean', 'verified_at' => 'datetime'];
    }

    public function maskedKey(): string
    {
        $key = (string) $this->api_key;

        return strlen($key) <= 4 ? '****' : str_repeat('*', 8).substr($key, -4);
    }
}
