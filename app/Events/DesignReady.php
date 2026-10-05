<?php

namespace App\Events;

use App\Models\DesignVersion;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DesignReady implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public DesignVersion $version, public string $userId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('users.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'DesignReady';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->version->getKey(),
            'design_id' => $this->version->design_id,
            'version' => $this->version->version,
            'image_url' => $this->version->image_url,
            'created_at' => $this->version->created_at?->toIso8601String(),
        ];
    }
}
