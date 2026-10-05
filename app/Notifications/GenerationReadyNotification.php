<?php

namespace App\Notifications;

use App\Events\NotificationCreated as NotificationCreatedEvent;
use App\Models\ContentGeneration;
use App\Models\Notification;
use App\Models\User;

class GenerationReadyNotification
{
    public static function send(User $user, ContentGeneration $generation): Notification
    {
        $notification = Notification::create([
            'user_id' => $user->getKey(),
            'type' => 'generation.ready',
            'title' => 'جاهزة خطة المحتوى',
            'body' => 'اكتمل توليد المحتوى وأصبح جاهزًا للمراجعة.',
            'link' => null,
            'data' => ['generation_id' => $generation->getKey(), 'brand_id' => $generation->brand_id],
        ]);

        NotificationCreatedEvent::dispatch($notification);

        return $notification;
    }
}
