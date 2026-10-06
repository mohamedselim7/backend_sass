<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Log\Context\Repository as LogContext;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Assigns/propagates a request id and pushes it (plus user id) into the log
 * context so every log line for a request can be correlated, and structured
 * JSON log drivers emit it automatically without each call site passing it.
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id') ?: (string) Str::uuid();

        $request->attributes->set('request_id', $requestId);

        if (class_exists(LogContext::class)) {
            \Illuminate\Support\Facades\Log::withContext([
                'request_id' => $requestId,
                'user_id' => $request->user()?->getKey(),
            ]);
        }

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
