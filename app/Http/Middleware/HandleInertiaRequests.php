<?php

namespace App\Http\Middleware;

use App\Models\SupportThread;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'admin';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Shared props. Permissions are computed on the server so the React side
     * only ever renders what the backend already allows.
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar_url' => $user->avatar_url,
                    'roles' => $user->getRoleNames()->values(),
                ] : null,
                'can' => [
                    'manageUsers' => (bool) $user?->hasRole('admin'),
                    'managePlans' => (bool) $user?->hasRole('admin'),
                    'manageSettings' => (bool) $user?->hasRole('admin'),
                    'handleSupport' => (bool) $user?->hasAnyRole(['admin', 'support']),
                ],
            ],
            'locale' => $request->session()->get('locale', $user?->locale ?? 'ar'),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'badges' => fn () => $user && $user->hasAnyRole(['admin', 'support'])
                ? ['support' => SupportThread::whereIn('status', ['open', 'pending'])->count()]
                : ['support' => 0],
        ]);
    }
}
