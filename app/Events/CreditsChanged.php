<?php

namespace App\Events;

use App\Models\CreditWallet;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CreditsChanged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public CreditWallet $wallet) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('users.'.$this->wallet->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'CreditsChanged';
    }

    public function broadcastWith(): array
    {
        return [
            'balance' => (int) $this->wallet->balance,
            'lifetime_granted' => (int) $this->wallet->lifetime_granted,
            'lifetime_spent' => (int) $this->wallet->lifetime_spent,
        ];
    }
}
