<?php

namespace App\Notifications;

use App\Events\NotificationCreated as NotificationCreatedEvent;
use App\Models\DesignVersion;
use App\Models\Notification;
use App\Models\User;

class DesignReadyNotification
{
    public static function send(User $user, DesignVersion $version): Notification
    {
        $notification = Notification::create([
            'user_id' => $user->getKey(),
            'type' => 'design.ready',
            'title' => 'التصميم جاهز',
            'body' => 'أصبحت نسخة جديدة من التصميم جاهزة للعرض.',
            'link' => null,
            'data' => ['design_id' => $version->design_id, 'version_id' => $version->getKey()],
        ]);

        NotificationCreatedEvent::dispatch($notification);

        return $notification;
    }
}
