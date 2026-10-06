<?php

namespace App\Jobs;

use App\Events\NotificationCreated;
use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Persists and broadcasts a notification off the request/response cycle, for
 * callers that already have the row data but don't want to block on the
 * broadcast round-trip (e.g. bulk admin broadcasts).
 */
class SendNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [5, 20, 60];

    /**
     * @param  array{user_id?:?string,type:string,title:string,body?:?string,link?:?string,data?:array}  $attributes
     */
    public function __construct(public array $attributes)
    {
        $this->onQueue(config('queue.names.notifications', 'notifications'));
    }

    public function handle(): void
    {
        $notification = Notification::create([
            'user_id' => $this->attributes['user_id'] ?? null,
            'type' => $this->attributes['type'],
            'title' => $this->attributes['title'],
            'body' => $this->attributes['body'] ?? null,
            'link' => $this->attributes['link'] ?? null,
            'data' => $this->attributes['data'] ?? [],
        ]);

        NotificationCreated::dispatch($notification);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendNotificationJob failed', [
            'attributes' => $this->attributes,
            'error' => $exception->getMessage(),
        ]);
    }
}
