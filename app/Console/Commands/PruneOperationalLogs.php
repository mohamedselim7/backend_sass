<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\UsageLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Deletes old operational rows: usage_logs, activity_logs, failed_jobs. */
class PruneOperationalLogs extends Command
{
    protected $signature = 'iden:prune-logs {--days=}';

    protected $description = 'Prune usage logs, activity logs and failed jobs older than the retention window';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('ai.log_retention_days', 180));
        $cutoff = now()->subDays($days);

        $usage = UsageLog::where('created_at', '<', $cutoff)->delete();
        $activity = ActivityLog::where('created_at', '<', $cutoff)->delete();
        $failed = DB::table('failed_jobs')->where('failed_at', '<', $cutoff)->delete();

        $this->info("Pruned usage_logs={$usage} activity_logs={$activity} failed_jobs={$failed} (older than {$days}d)");

        return self::SUCCESS;
    }
}
