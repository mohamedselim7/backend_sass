<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Models\NotificationState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $notifications = Notification::forUser($request->user())
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->latest()
            ->paginate(min($request->integer('per_page', 25), 100));

        return NotificationResource::collection($notifications);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = Notification::forUser($request->user())->whereNull('read_at')->count();

        return response()->json(['unread' => $count]);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        $user = $request->user();

        if ($notification->user_id !== null && $notification->user_id !== $user->getKey()) {
            abort(403);
        }

        // Global announcements record read state per user instead of on the row.
        if ($notification->user_id === null) {
            NotificationState::updateOrCreate(
                ['user_id' => $user->getKey(), 'notification_id' => $notification->getKey()],
                ['read_at' => now()],
            );
        } else {
            $notification->update(['read_at' => now()]);
        }

        return response()->json(['message' => 'تم وضع علامة مقروء.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->getKey())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'تم وضع علامة مقروء على الكل.']);
    }
}
