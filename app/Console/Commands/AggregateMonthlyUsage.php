<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Rolls last month's usage_logs into usage_monthly_aggregates for cheap long-term reporting. */
class AggregateMonthlyUsage extends Command
{
    protected $signature = 'iden:aggregate-monthly-usage {--month=} {--year=}';

    protected $description = 'Aggregate usage_logs into usage_monthly_aggregates for a given month (defaults to last month)';

    public function handle(): int
    {
        $target = $this->option('year') && $this->option('month')
            ? now()->setDate((int) $this->option('year'), (int) $this->option('month'), 1)
            : now()->subMonthNoOverflow();

        $start = $target->copy()->startOfMonth();
        $end = $target->copy()->endOfMonth();

        $rows = DB::table('usage_logs')
            ->selectRaw('user_id, feature, provider,
                SUM(COALESCE(tokens, 0)) as total_tokens,
                SUM(COALESCE(images, 0)) as total_images,
                SUM(COALESCE(cost, 0)) as total_cost,
                COUNT(*) as operation_count')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('user_id', 'feature', 'provider')
            ->get();

        foreach ($rows as $row) {
            DB::table('usage_monthly_aggregates')->updateOrInsert(
                [
                    'user_id' => $row->user_id,
                    'year' => $start->year,
                    'month' => $start->month,
                    'feature' => $row->feature,
                    'provider' => $row->provider,
                ],
                [
                    'total_tokens' => $row->total_tokens,
                    'total_images' => $row->total_images,
                    'total_cost' => $row->total_cost,
                    'operation_count' => $row->operation_count,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        $this->info("Aggregated {$rows->count()} usage rows for {$start->format('Y-m')}");

        return self::SUCCESS;
    }
}
