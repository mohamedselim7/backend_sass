<?php

namespace App\Services\Admin;

use App\Models\ContentPost;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Every figure the Admin dashboard shows is aggregated here, from MySQL,
 * using the subscription rules already enforced by the product
 * (status in active|trialing and ends_at in the future or open-ended).
 * No mock data, no client-side maths.
 */
class AdminMetricsService
{
    /** @return array<string, mixed> */
    public function overview(): array
    {
        $now = Carbon::now();
        $monthStart = $now->copy()->startOfMonth();

        return [
            'users' => [
                'total' => User::count(),
                'active' => User::where('status', 'active')->count(),
                'new_this_month' => User::where('created_at', '>=', $monthStart)->count(),
                'new_last_month' => User::whereBetween('created_at', [
                    $monthStart->copy()->subMonth(), $monthStart,
                ])->count(),
            ],
            'subscriptions' => [
                'active' => $this->activeSubscriberCount(),
                'trialing' => Subscription::where('status', 'trialing')->count(),
                'cancelled' => Subscription::whereNotNull('cancelled_at')->count(),
                'expiring_soon' => $this->expiringSoonCount(),
            ],
            'revenue' => [
                'this_month' => $this->revenueBetween($monthStart, $now),
                'last_month' => $this->revenueBetween(
                    $monthStart->copy()->subMonth(),
                    $monthStart
                ),
                'lifetime' => (float) Payment::where('status', 'paid')->sum('amount'),
                'currency' => Payment::where('status', 'paid')->value('currency') ?? config('payments.currency', 'SAR'),
            ],
            'payments' => [
                'successful_this_month' => Payment::where('status', 'paid')
                    ->where('paid_at', '>=', $monthStart)->count(),
                'failed_this_month' => Payment::where('status', 'failed')
                    ->where('created_at', '>=', $monthStart)->count(),
                'pending' => Payment::where('status', 'pending')->count(),
            ],
            'support' => [
                'open_threads' => SupportThread::open()->count(),
                'unread_messages' => (int) SupportThread::sum('unread_for_admin'),
                'unassigned' => SupportThread::open()->whereNull('assigned_to')->count(),
                'replied_today' => SupportMessage::where('author_type', 'agent')
                    ->where('created_at', '>=', $now->copy()->startOfDay())->count(),
            ],
            'content' => [
                'posts' => ContentPost::count(),
                'published' => ContentPost::where('status', 'published')->count(),
                'this_month' => ContentPost::where('created_at', '>=', $monthStart)->count(),
            ],
        ];
    }

    /** Users whose subscription is currently active by the existing business rules. */
    public function activeSubscriberCount(): int
    {
        return Subscription::query()
            ->whereIn('status', ['active', 'trialing'])
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', Carbon::now()))
            ->distinct()
            ->count('user_id');
    }

    public function expiringSoonCount(int $days = 14): int
    {
        return Subscription::query()
            ->whereIn('status', ['active', 'trialing'])
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [Carbon::now(), Carbon::now()->addDays($days)])
            ->count();
    }

    /** @return array<int, array{month: string, total: float}> */
    public function revenueSeries(int $months = 12): array
    {
        $from = Carbon::now()->startOfMonth()->subMonths($months - 1);

        $rows = Payment::query()
            ->where('status', 'paid')
            ->where('paid_at', '>=', $from)
            ->selectRaw("DATE_FORMAT(paid_at, '%Y-%m') as month, SUM(amount) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        return $this->fillMonths($from, $months, fn (string $key) => (float) ($rows[$key] ?? 0));
    }

    /** @return array<int, array{month: string, total: float}> */
    public function userGrowthSeries(int $months = 12): array
    {
        $from = Carbon::now()->startOfMonth()->subMonths($months - 1);

        $rows = User::query()
            ->where('created_at', '>=', $from)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        return $this->fillMonths($from, $months, fn (string $key) => (float) ($rows[$key] ?? 0));
    }

    /** @return array<int, array{status: string, total: int}> */
    public function subscriptionStatusBreakdown(): array
    {
        return Subscription::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['status' => (string) $row->status, 'total' => (int) $row->total])
            ->all();
    }

    private function revenueBetween(Carbon $from, Carbon $to): float
    {
        return (float) Payment::where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount');
    }

    /**
     * @param  callable(string): float  $resolver
     * @return array<int, array{month: string, total: float}>
     */
    private function fillMonths(Carbon $from, int $months, callable $resolver): array
    {
        $series = [];

        for ($i = 0; $i < $months; $i++) {
            $key = $from->copy()->addMonths($i)->format('Y-m');
            $series[] = ['month' => $key, 'total' => $resolver($key)];
        }

        return $series;
    }
}
