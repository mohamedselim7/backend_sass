<?php

namespace App\Jobs;

use App\Models\ScheduledPost;
use App\Services\Social\SocialPublisher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Publishes a single scheduled post via SocialPublisher, mirroring
 * ScheduleService::publish()'s success/failure handling and events so a post
 * can also be published through the queue (e.g. from the manual "publish now"
 * endpoint) instead of only from the ScheduleService::publishDue() sweep.
 */
class PublishScheduledPostJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [15, 60, 180];

    public function __construct(public string $scheduledPostId) {}

    public function handle(SocialPublisher $publisher): void
    {
        $scheduled = ScheduledPost::findOrFail($this->scheduledPostId);

        if ($scheduled->status === \App\Enums\ScheduleStatus::Published) {
            return;
        }

        try {
            $result = $publisher->publish($scheduled);

            $scheduled->update([
                'status' => \App\Enums\ScheduleStatus::Published->value,
                'published_at' => now(),
                'external_post_id' => $result['external_post_id'] ?? null,
                'last_error' => null,
            ]);

            \App\Events\ScheduledPostPublished::dispatch($scheduled);

            if ($scheduled->user) {
                \App\Notifications\ScheduledPostPublishedNotification::send($scheduled->user, $scheduled);
            }
        } catch (\Throwable $e) {
            $scheduled->update([
                'status' => \App\Enums\ScheduleStatus::Failed->value,
                'last_error' => mb_substr($e->getMessage(), 0, 1000),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('PublishScheduledPostJob failed', [
            'scheduled_post_id' => $this->scheduledPostId,
            'error' => $exception->getMessage(),
        ]);
    }
}
