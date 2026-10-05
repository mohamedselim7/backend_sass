<?php

namespace App\Console\Commands;

use App\Jobs\PublishScheduledPostJob;
use App\Services\ScheduleService;
use Illuminate\Console\Command;

class PublishDuePosts extends Command
{
    protected $signature = 'iden:publish-due-posts {--limit=25}';

    protected $description = 'Dispatch a queued publish job for every scheduled post whose time has come';

    public function handle(ScheduleService $scheduler): int
    {
        $claimed = $scheduler->claimDue((int) $this->option('limit'));

        foreach ($claimed as $scheduledPost) {
            PublishScheduledPostJob::dispatch($scheduledPost->getKey());
        }

        $this->info('Dispatched '.count($claimed).' publish job(s).');

        return self::SUCCESS;
    }
}
