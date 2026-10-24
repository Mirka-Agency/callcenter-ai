<?php

use App\Http\Middleware\CaptureLeadAttribution;
use App\Http\Middleware\EnsureEmployee;
use App\Http\Middleware\EnsureEmployer;
use App\Http\Middleware\EnsureLandingHost;
use App\Http\Middleware\EnsureOrganizationCrmMode;
use App\Http\Middleware\HandleSeoRedirects;
use App\Http\Middleware\ImpersonationMiddleware;
use App\Http\Middleware\RecordUserActivity;
use App\Http\Middleware\TrustCapRoverTlsTermination;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->prefix('app')
                ->name('employer.')
                ->group(base_path('routes/employer.php'));
            Route::middleware('web')
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
            TrustCapRoverTlsTermination::class,
        ]);

        $middleware->append(HandleSeoRedirects::class);

        // External VoIP providers POST CDR events without a CSRF cookie.
        $middleware->validateCsrfTokens(except: [
            'webhooks/voip/*',
        ]);

        $middleware->alias([
            'employer' => EnsureEmployer::class,
            'employee' => EnsureEmployee::class,
            'organization-crm' => EnsureOrganizationCrmMode::class,
            'landing-host' => EnsureLandingHost::class,
        ]);

        $middleware->web(append: [
            ImpersonationMiddleware::class,
            RecordUserActivity::class,
            CaptureLeadAttribution::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
