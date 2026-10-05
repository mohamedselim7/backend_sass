<?php

namespace App\Events;

use App\Models\SupportThread;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Conversation-level changes (unread counters, status, assignment, last
 * message) so both inbox lists stay current without a refresh or polling.
 */
class SupportThreadUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(public SupportThread $thread, public ?string $lastMessage = null) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('users.'.$this->thread->user_id),
            new PrivateChannel('admin'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'support.thread.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->thread->getKey(),
            'subject' => $this->thread->subject,
            'status' => $this->thread->status,
            'priority' => $this->thread->priority,
            'assigned_to' => $this->thread->assigned_to,
            'unread_for_user' => (int) $this->thread->unread_for_user,
            'unread_for_admin' => (int) $this->thread->unread_for_admin,
            'last_message_at' => $this->thread->last_message_at?->toIso8601String(),
            'last_message' => $this->lastMessage !== null ? mb_substr($this->lastMessage, 0, 140) : null,
            'user_unread_total' => (int) SupportThread::where('user_id', $this->thread->user_id)->sum('unread_for_user'),
            'admin_unread_total' => (int) SupportThread::sum('unread_for_admin'),
        ];
    }
}
