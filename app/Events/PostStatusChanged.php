<?php

namespace App\Events;

use App\Models\ContentPost;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostStatusChanged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public ContentPost $post) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('users.'.$this->post->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'PostStatusChanged';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->post->getKey(),
            'plan_id' => $this->post->plan_id,
            'status' => $this->post->status->value,
            'updated_at' => $this->post->updated_at?->toIso8601String(),
        ];
    }
}
