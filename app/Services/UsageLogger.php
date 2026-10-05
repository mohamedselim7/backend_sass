<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\UsageLog;
use App\Models\User;

/** Writes the audit + usage trail the admin dashboard reads. */
class UsageLogger
{
    public function activity(?User $user, string $action, array $attributes = []): ActivityLog
    {
        return ActivityLog::create(array_merge([
            'user_id' => $user?->getKey(),
            'actor' => $user?->email,
            'action' => $action,
        ], $attributes));
    }

    public function usage(?User $user, string $feature, array $attributes = []): UsageLog
    {
        return UsageLog::create(array_merge([
            'user_id' => $user?->getKey(),
            'feature' => $feature,
            'status' => 'success',
        ], $attributes));
    }
}
