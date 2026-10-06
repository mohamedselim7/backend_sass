<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatThread extends Model
{
    use HasFactory;
    use HasUuidKey;
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'brand_id', 'title', 'mode', 'provider', 'model', 'last_message_at',
        'summary', 'summary_until_message_id', 'summary_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'summary_updated_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'thread_id')->orderBy('created_at');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->where('user_id', $user->getKey());
    }
}
