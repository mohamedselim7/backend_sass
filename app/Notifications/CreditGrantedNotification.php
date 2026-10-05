<?php

namespace App\Notifications;

use App\Events\NotificationCreated as NotificationCreatedEvent;
use App\Models\Notification;
use App\Models\User;

/**
 * Persists a "credits granted" row on the existing `notifications` table and
 * broadcasts it on the user's private channel. Not a Laravel ShouldQueue
 * Notification (there is no `notifiable_type` polymorphic table here) — it
 * writes directly through the app's own Notification model instead.
 */
class CreditGrantedNotification
{
    public static function send(User $user, int $amount, int $balance, string $reason = 'credit_granted'): Notification
    {
        $notification = Notification::create([
            'user_id' => $user->getKey(),
            'type' => 'credit.granted',
            'title' => 'تم إضافة رصيد',
            'body' => "تمت إضافة {$amount} نقطة إلى رصيدك.",
            'link' => null,
            'data' => ['amount' => $amount, 'balance' => $balance, 'reason' => $reason],
        ]);

        NotificationCreatedEvent::dispatch($notification);

        return $notification;
    }
}
