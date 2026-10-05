<?php

namespace App\Services\Admin;

use App\Events\SupportMessageSent;
use App\Events\SupportThreadUpdated;
use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Single place where support conversations are mutated, so the REST API (users)
 * and the Inertia Admin (agents) always follow the same rules.
 */
class SupportService
{
    public function openThread(User $user, string $subject, string $body, string $category = 'general', string $priority = 'normal', array $attachments = []): SupportThread
    {
        return DB::transaction(function () use ($user, $subject, $body, $category, $priority, $attachments) {
            $thread = SupportThread::create([
                'user_id' => $user->getKey(),
                'subject' => $subject,
                'category' => $category,
                'priority' => $priority,
                'status' => 'open',
                'unread_for_admin' => 0,  
                'unread_for_user' => 0, 
            ]);

            $this->addMessage($thread, $user, $body, 'user', $attachments);

            return $thread->refresh();
        });
    }

    public function addMessage(SupportThread $thread, ?User $sender, string $body, string $authorType, array $attachments = []): SupportMessage
    {
        $message = DB::transaction(function () use ($thread, $sender, $body, $authorType, $attachments) {
            $message = SupportMessage::create([
                'thread_id' => $thread->getKey(),
                'sender_id' => $sender?->getKey(),
                'author_type' => $authorType,
                'body' => $body,
                'attachments' => $attachments ?: null,
            ]);

            $thread->forceFill([
                'last_message_at' => $message->created_at,
                'unread_for_admin' => $authorType === 'user'
                    ? $thread->unread_for_admin + 1
                    : 0,
                'unread_for_user' => $authorType === 'agent'
                    ? $thread->unread_for_user + 1
                    : $thread->unread_for_user,
                'status' => $authorType === 'agent' ? 'pending' : 'open',
            ])->save();

            return $message;
        });

        // Realtime delivery is best-effort: a missing queue/Pusher setup must
        // never turn an already-saved support message into a failed request.
        try {
            broadcast(new SupportMessageSent($message->load('sender', 'thread')))->toOthers();
        } catch (\Throwable $exception) {
            report($exception);
        }

        $this->broadcastThread($thread->refresh(), $body !== '' ? $body : '📷');

        return $message;
    }

    /** Best-effort conversation update (unread, status, last message) for both inboxes. */
    public function broadcastThread(SupportThread $thread, ?string $lastMessage = null): void
    {
        try {
            broadcast(new SupportThreadUpdated($thread, $lastMessage));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    public function markReadForAgent(SupportThread $thread): void
    {
        $thread->messages()->where('author_type', 'user')->whereNull('read_at')->update(['read_at' => now()]);
        $thread->forceFill(['unread_for_admin' => 0])->save();
        $this->broadcastThread($thread);
    }

    public function markReadForUser(SupportThread $thread): void
    {
        $thread->messages()->where('author_type', '!=', 'user')->whereNull('read_at')->update(['read_at' => now()]);
        $thread->forceFill(['unread_for_user' => 0])->save();
        $this->broadcastThread($thread);
    }

    public function setStatus(SupportThread $thread, string $status): SupportThread
    {
        $thread->forceFill(['status' => $status])->save();
        $this->broadcastThread($thread);

        return $thread;
    }

    public function assign(SupportThread $thread, ?User $agent): SupportThread
    {
        $thread->forceFill(['assigned_to' => $agent?->getKey()])->save();
        $this->broadcastThread($thread);

        return $thread;
    }
}
