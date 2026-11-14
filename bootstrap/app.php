<?php

use App\Http\Middleware\AddAppVersionHeader;
use App\Modules\Identity\Http\Middleware\EnsureAccountIsActive;
use App\Modules\Identity\Http\Middleware\EnsureTwoFactorEnrolled;
use App\Modules\Property\Http\Middleware\ResolvePropertyContext;
use App\Support\Http\Problem;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Private Backend-for-Frontend: one route file per module (spec §8.1, §10.1).
            Route::middleware('bff')->prefix('bff')->name('bff.')->group(function (): void {
                foreach (glob(base_path('routes/bff/*.php')) ?: [] as $file) {
                    require $file;
                }
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(AddAppVersionHeader::class);

        // TLS terminates at the Kubernetes ingress (spec §3.3): trust its forwarded headers
        // from private cluster ranges only, so URLs and secure cookies use https.
        $middleware->trustProxies(at: ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '127.0.0.1']);

        $middleware->web(append: [
            EnsureAccountIsActive::class,
        ]);

        // Sanctum SPA mode: session + CSRF only for requests from the first-party origin.
        $middleware->group('bff', [
            EnsureFrontendRequestsAreStateful::class,
            'throttle:bff',
            SubstituteBindings::class,
            EnsureAccountIsActive::class,
            EnsureTwoFactorEnrolled::class,
        ]);

        // X-Property-Id validation (spec §2.7): property | property:optional | property:default.
        $middleware->alias(['property' => ResolvePropertyContext::class]);

        // The SPA owns the sign-in page; unauthenticated JSON requests receive 401.
        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('bff/*') || $request->expectsJson(),
        );

        // RFC 9457 problem+json for everything the SPA calls (spec §8.4).
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('bff/*') && ! $request->expectsJson()) {
                return null;
            }

            return match (true) {
                $e instanceof ValidationException => Problem::response(
                    422,
                    'validation_failed',
                    'Validation failed',
                    $e->getMessage(),
                    ['errors' => collect($e->errors())
                        ->flatMap(fn (array $messages, string $field) => array_map(
                            fn (string $message): array => ['field' => $field, 'message' => $message],
                            $messages,
                        ))
                        ->values()
                        ->all()],
                ),
                $e instanceof AuthenticationException => Problem::response(401, 'unauthenticated', 'Unauthenticated'),
                $e instanceof AuthorizationException => Problem::response(403, 'forbidden', 'Forbidden', $e->getMessage()),
                $e instanceof ModelNotFoundException => Problem::response(404, 'not_found', 'Not found'),
                $e instanceof TokenMismatchException => Problem::response(419, 'session_expired', 'Session expired'),
                $e instanceof HttpExceptionInterface => Problem::response(
                    $e->getStatusCode(),
                    Problem::codeFor($e->getStatusCode()),
                    Problem::titleFor($e->getStatusCode()),
                    $e->getMessage() !== '' ? $e->getMessage() : null,
                    [],
                    $e->getHeaders(),
                ),
                default => config('app.debug') ? null : Problem::response(500, 'server_error', 'Server error'),
            };
        });
    })->create();
