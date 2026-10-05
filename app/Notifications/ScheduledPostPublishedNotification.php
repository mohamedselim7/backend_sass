<?php

namespace App\Notifications;

use App\Events\NotificationCreated as NotificationCreatedEvent;
use App\Models\Notification;
use App\Models\ScheduledPost;
use App\Models\User;

class ScheduledPostPublishedNotification
{
    public static function send(User $user, ScheduledPost $scheduledPost): Notification
    {
        $notification = Notification::create([
            'user_id' => $user->getKey(),
            'type' => 'schedule.published',
            'title' => 'تم نشر المنشور المجدول',
            'body' => "تم نشر منشورك على {$scheduledPost->platform}.",
            'link' => null,
            'data' => [
                'scheduled_post_id' => $scheduledPost->getKey(),
                'platform' => $scheduledPost->platform,
                'external_post_id' => $scheduledPost->external_post_id,
            ],
        ]);

        NotificationCreatedEvent::dispatch($notification);

        return $notification;
    }
}
