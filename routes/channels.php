<?php

use App\Models\SupportThread;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// A user may only ever listen on their own channel.
Broadcast::channel('users.{userId}', fn (User $user, string $userId) => $user->getKey() === $userId);

// Admin-only stream for the operations dashboard.
Broadcast::channel('admin', fn (User $user) => $user->hasAnyRole(['admin', 'support']));

// One support conversation: its owner, plus admins and support agents.
Broadcast::channel('support.{threadId}', function (User $user, string $threadId) {
    $thread = SupportThread::find($threadId);

    return $thread !== null
        && ($user->hasAnyRole(['admin', 'support']) || $thread->user_id === $user->getKey());
});
