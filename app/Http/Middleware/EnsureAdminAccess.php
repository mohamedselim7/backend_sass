<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authorisation gate for the Inertia Admin. Roles are resolved server side
 * through Spatie Permission; the React layer never decides access.
 */
class EnsureAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasAnyRole(['admin', 'support'])) {
            abort(403);
        }

        return $next($request);
    }
}
