<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tymon\JWTAuth\Exceptions\JWTException;


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // The user application keeps its REST API surface untouched.
        api: __DIR__ . '/../routes/api.php',
        // The Inertia Admin application is the only web (session) surface.
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    // Broadcasting auth behind the JWT api guard: the React client (Laravel
    // Echo + Pusher) authenticates with `Authorization: Bearer <jwt>`, not a
    // web session, so `/broadcasting/auth` must run through `auth:api`
    // rather than the framework's default `web` guard.
    // The Inertia Admin registers its own session-guarded endpoint at
    // /admin/broadcasting/auth inside routes/web.php.
    ->withBroadcasting(
        __DIR__ . '/../routes/channels.php',
        ['middleware' => ['auth:api']],
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\SetAdminLocale::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'verified.api' => \App\Http\Middleware\EnsureEmailIsVerifiedApi::class,
            'admin' => \App\Http\Middleware\EnsureAdminAccess::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('admin.login'));
        $middleware->throttleApi('api');
    })

    ->withExceptions(function (Exceptions $exceptions) {
        // One consistent JSON error envelope for the whole API.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof AuthenticationException || $e instanceof JWTException) {
                return response()->json(['message' => 'Unauthenticated.', 'error' => 'unauthenticated'], 401);
            }

            if ($e instanceof AuthorizationException) {
                return response()->json(['message' => 'This action is unauthorized.', 'error' => 'forbidden'], 403);
            }

            if ($e instanceof ModelNotFoundException) {
                return response()->json(['message' => 'Resource not found.', 'error' => 'not_found'], 404);
            }

            if ($e instanceof ValidationException) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => $e->errors(),
                ], 422);
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

            $message = match ($status) {
                401 => 'Unauthenticated.',
                403 => 'This action is unauthorized.',
                404 => 'Resource not found.',
                429 => 'Too many requests. Please slow down.',
                500 => 'Server error.',
                default => $e->getMessage() ?: 'Request failed.',
            };

            if ($status === 500) {
                report($e);
            }

            $payload = ['message' => $message];
            if ($status === 500 && config('app.debug')) {
                $payload['debug'] = ['exception' => $e::class, 'detail' => $e->getMessage()];
            }

            return response()->json($payload, $status);
        });

        // The Inertia Admin renders its own React error screens so a failure
        // still looks like the product instead of a bare framework page.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if (! $request->is('admin') && ! $request->is('admin/*')) {
                return $response;
            }

            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return $response;
            }

            if (! in_array($response->getStatusCode(), [403, 404, 419, 429, 500, 503], true)) {
                return $response;
            }

            if ($response->getStatusCode() === 419) {
                return back()->with('error', 'Your session expired. Please try again.');
            }

            return Inertia::render('errors/ErrorPage', [
                'status' => $response->getStatusCode(),
            ])->toResponse($request)->setStatusCode($response->getStatusCode());
        });

    })->create();
