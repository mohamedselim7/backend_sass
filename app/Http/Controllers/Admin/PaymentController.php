<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->toString(),
            'status' => $request->string('status')->toString(),
            'gateway' => $request->string('gateway')->toString(),
            'from' => $request->string('from')->toString(),
            'to' => $request->string('to')->toString(),
        ];

        $query = Payment::query()
            ->with(['user:id,name,email', 'subscription.plan:id,name'])
            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $term = '%'.$filters['search'].'%';
                $q->where(fn ($inner) => $inner->where('reference', 'like', $term)
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'like', $term)->orWhere('name', 'like', $term)));
            })
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['gateway'] !== '', fn ($q) => $q->where('gateway', $filters['gateway']))
            ->when($filters['from'] !== '', fn ($q) => $q->where('created_at', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn ($q) => $q->where('created_at', '<=', $filters['to'].' 23:59:59'));

        $totals = (clone $query)
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as amount'))
            ->groupBy('status')->get()
            ->mapWithKeys(fn ($row) => [$row->status => [
                'count' => (int) $row->count,
                'amount' => (float) $row->amount,
            ]]);

        return Inertia::render('payments/Index', [
            'payments' => $query->latest()->paginate(25)->withQueryString()
                ->through(fn (Payment $p) => [
                    'id' => $p->getKey(),
                    'reference' => $p->reference,
                    'user' => $p->user?->only(['id', 'name', 'email']),
                    'plan' => $p->subscription?->plan?->only(['id', 'name']),
                    'amount' => (float) $p->amount,
                    'currency' => $p->currency,
                    'status' => $p->status,
                    'gateway' => $p->gateway,
                    'paid_at' => $p->paid_at?->toIso8601String(),
                    'created_at' => $p->created_at?->toIso8601String(),
                ]),
            'filters' => $filters,
            'totals' => $totals,
            'gateways' => Payment::query()->whereNotNull('gateway')->distinct()->orderBy('gateway')->pluck('gateway'),
        ]);
    }
}
