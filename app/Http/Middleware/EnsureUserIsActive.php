<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** A suspended account keeps a valid token but loses API access immediately. */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status !== 'active') {
            return response()->json([
                'message' => 'تم إيقاف هذا الحساب. تواصل مع الدعم.',
                'error' => 'account_suspended',
            ], 403);
        }

        return $next($request);
    }
}
