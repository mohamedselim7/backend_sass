<?php

namespace App\Notifications;

use App\Events\NotificationCreated as NotificationCreatedEvent;
use App\Models\Notification;
use App\Models\User;

/**
 * Sends a notification either to a single user or, when `$user` is null, as a
 * global announcement (user_id = null) broadcast on the public `announcements`
 * channel as well as being visible to everyone via Notification::forUser().
 */
class AdminBroadcastNotification
{
    public static function send(?User $user, string $title, string $body, ?string $link = null, array $data = []): Notification
    {
        $notification = Notification::create([
            'user_id' => $user?->getKey(),
            'type' => 'admin.broadcast',
            'title' => $title,
            'body' => $body,
            'link' => $link,
            'data' => $data,
        ]);

        NotificationCreatedEvent::dispatch($notification);

        return $notification;
    }
}
