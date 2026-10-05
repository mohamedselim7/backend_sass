<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationState extends Model
{
    use HasFactory;
    use HasUuidKey;

    protected $fillable = ['user_id', 'notification_id', 'read_at', 'dismissed_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'dismissed_at' => 'datetime'];
    }
}
