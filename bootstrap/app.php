<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withEvents(discover: false)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(base_path('routes/web_b2c.php'));
            Route::middleware('web')->group(base_path('routes/web_b2b.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'tenant'              => \App\Http\Middleware\TenantMiddleware::class,
            'permission'          => \App\Http\Middleware\CheckPermission::class,
            'portal'              => \App\Http\Middleware\PortalContextMiddleware::class,
            'ensureAccountType'   => \App\Http\Middleware\EnsureAccountTypeMiddleware::class,
            'userType'            => \App\Http\Middleware\EnsureUserTypeMiddleware::class,
            'tenantContext'       => \App\Http\Middleware\ResolveTenantContextMiddleware::class,
            'legacyExternalSurface' => \App\Http\Middleware\LegacyExternalSurfaceLockdown::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $exception, Request $request) {
            $isBrowserRequest = ! $request->expectsJson() && ! $request->is('api/*');
            $isHandledClientException = $exception instanceof ValidationException
                || $exception instanceof AuthenticationException
                || $exception instanceof AuthorizationException
                || $exception instanceof NotFoundHttpException;

            if (! $isBrowserRequest || $isHandledClientException) {
                return null;
            }

            $statusCode = method_exists($exception, 'getStatusCode')
                ? (int) $exception->getStatusCode()
                : 500;

            if ($statusCode < 500) {
                return null;
            }

            return response()->view('errors.500', [
                'exception' => $exception,
            ], 500);
        });
    })
    ->create();
