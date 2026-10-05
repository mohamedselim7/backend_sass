<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialApp extends Model
{
    use HasFactory;
    use HasUuidKey;

    protected $fillable = [
        'user_id', 'platform', 'label', 'app_id', 'app_secret', 'scopes', 'redirect_uri', 'is_active',
    ];

    protected $hidden = ['app_secret'];

    protected function casts(): array
    {
        return [
            // Secrets are encrypted at rest and never serialised to API responses.
            'app_secret' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }
}
