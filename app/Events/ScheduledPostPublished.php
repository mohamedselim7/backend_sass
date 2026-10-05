<?php

namespace App\Events;

use App\Models\ScheduledPost;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ScheduledPostPublished implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public ScheduledPost $scheduledPost) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('users.'.$this->scheduledPost->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'ScheduledPostPublished';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->scheduledPost->getKey(),
            'status' => $this->scheduledPost->status->value,
            'platform' => $this->scheduledPost->platform,
            'external_post_id' => $this->scheduledPost->external_post_id,
            'published_at' => $this->scheduledPost->published_at?->toIso8601String(),
        ];
    }
}
