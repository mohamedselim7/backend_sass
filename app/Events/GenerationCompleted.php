<?php

namespace App\Events;

use App\Models\ContentGeneration;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GenerationCompleted implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public ContentGeneration $generation) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('users.'.$this->generation->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'GenerationCompleted';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->generation->getKey(),
            'brand_id' => $this->generation->brand_id,
            'status' => $this->generation->status,
            'plans_count' => $this->generation->plans()->count(),
            'created_at' => $this->generation->created_at?->toIso8601String(),
        ];
    }
}
