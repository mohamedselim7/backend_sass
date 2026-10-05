<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Events\NotificationCreated;
use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    /** Omitting user_id sends the notification to everyone. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'type' => ['nullable', 'string', 'max:64'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
            'link' => ['nullable', 'url', 'max:1024'],
            'data' => ['nullable', 'array'],
        ]);

        $notification = Notification::create($data);
        NotificationCreated::dispatch($notification);

        return (new NotificationResource($notification))->response()->setStatusCode(201);
    }
}
