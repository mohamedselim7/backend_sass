<?php

namespace App\Services;

use App\Enums\ScheduleStatus;
use App\Events\ScheduledPostPublished;
use App\Exceptions\DomainException;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Models\User;
use App\Notifications\ScheduledPostPublishedNotification;
use App\Services\Social\SocialPublisher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ScheduleService
{
    public function __construct(
        private readonly SocialPublisher $publisher,
        private readonly UsageLogger $logger,
    ) {}

    /** @param array<string, mixed> $data */
    public function schedule(User $user, array $data): ScheduledPost
    {
        $when = Carbon::parse($data['scheduled_at'], $data['timezone'] ?? 'UTC')->utc();

        if ($when->isPast()) {
            throw new DomainException('لا يمكن جدولة منشور في وقت مضى.', 422, 'schedule_in_past');
        }

        if (! empty($data['social_account_id'])) {
            $owned = SocialAccount::where('id', $data['social_account_id'])
                ->where('user_id', $user->getKey())
                ->exists();

            if (! $owned) {
                throw new DomainException('الحساب المختار غير متاح.', 403, 'social_account_forbidden');
            }
        }

        $scheduled = ScheduledPost::create([
            'user_id' => $user->getKey(),
            'brand_id' => $data['brand_id'] ?? null,
            'post_id' => $data['post_id'] ?? null,
            'platform' => $data['platform'],
            'social_account_id' => $data['social_account_id'] ?? null,
            'caption' => $data['caption'] ?? null,
            'media' => $data['media'] ?? [],
            'hashtags' => $data['hashtags'] ?? [],
            'scheduled_at' => $when,
            'timezone' => $data['timezone'] ?? 'UTC',
            'status' => ScheduleStatus::Scheduled->value,
        ]);

        $this->logger->activity($user, 'schedule.created', [
            'entity' => 'scheduled_post',
            'feature' => 'scheduler',
            'brand_id' => $scheduled->brand_id,
            'details' => ['scheduled_post_id' => $scheduled->getKey(), 'platform' => $scheduled->platform],
        ]);

        return $scheduled;
    }

    public function cancel(ScheduledPost $scheduled): ScheduledPost
    {
        if ($scheduled->status === ScheduleStatus::Published) {
            throw new DomainException('تم نشر هذا المنشور بالفعل.', 409, 'already_published');
        }

        $scheduled->update(['status' => ScheduleStatus::Cancelled->value]);

        return $scheduled;
    }

    /**
     * Claim and publish everything that is due. The status flip to `publishing`
     * happens under a lock so two workers never publish the same row twice.
     */
    public function publishDue(int $limit = 25): int
    {
        $published = 0;

        foreach ($this->claimDue($limit) as $claimed) {
            if ($this->publish($claimed)) {
                $published++;
            }
        }

        return $published;
    }

    /**
     * Claim every due row under a lock (flipping it to `publishing` so no
     * other worker double-claims it) without actually publishing yet. Used by
     * `iden:publish-due-posts` to hand each claimed id to a queued
     * PublishScheduledPostJob instead of publishing inline.
     *
     * @return array<int, ScheduledPost>
     */
    public function claimDue(int $limit = 25): array
    {
        $claimed = [];

        ScheduledPost::due()->orderBy('scheduled_at')->limit($limit)->pluck('id')
            ->each(function (string $id) use (&$claimed) {
                $row = DB::transaction(function () use ($id) {
                    $row = ScheduledPost::where('id', $id)->lockForUpdate()->first();

                    if (! $row || $row->status !== ScheduleStatus::Scheduled) {
                        return null;
                    }

                    $row->update(['status' => ScheduleStatus::Publishing->value, 'attempts' => $row->attempts + 1]);

                    return $row;
                });

                if ($row) {
                    $claimed[] = $row;
                }
            });

        return $claimed;
    }

    protected function publish(ScheduledPost $scheduled): bool
    {
        try {
            $result = $this->publisher->publish($scheduled);

            $scheduled->update([
                'status' => ScheduleStatus::Published->value,
                'published_at' => now(),
                'external_post_id' => $result['external_post_id'] ?? null,
                'last_error' => null,
            ]);

            ScheduledPostPublished::dispatch($scheduled);

            if ($scheduled->user) {
                ScheduledPostPublishedNotification::send($scheduled->user, $scheduled);
            }

            return true;
        } catch (\Throwable $e) {
            $scheduled->update([
                'status' => ScheduleStatus::Failed->value,
                'last_error' => mb_substr($e->getMessage(), 0, 1000),
            ]);

            return false;
        }
    }
}
