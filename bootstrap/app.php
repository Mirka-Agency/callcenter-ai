<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            \Illuminate\Support\Facades\Route::middleware('web')
                ->prefix('app')
                ->name('employer.')
                ->group(base_path('routes/employer.php'));
            \Illuminate\Support\Facades\Route::middleware('web')
                ->prefix('workspace')
                ->name('employee.')
                ->group(base_path('routes/employee.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // CapRover terminates TLS; trust proxy proto (not host) so signed URLs validate.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB,
        );

        $middleware->web(prepend: [
            \App\Http\Middleware\TrustCapRoverTlsTermination::class,
        ]);

        // External VoIP providers POST CDR events without a CSRF cookie.
        $middleware->validateCsrfTokens(except: [
            'webhooks/voip/*',
        ]);

        $middleware->alias([
            'employer' => \App\Http\Middleware\EnsureEmployer::class,
            'employee' => \App\Http\Middleware\EnsureEmployee::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\ImpersonationMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
