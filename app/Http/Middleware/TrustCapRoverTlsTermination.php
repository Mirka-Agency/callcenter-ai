<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CapRover terminates TLS in front of the container. PHP/nginx only see HTTP,
 * but signed URLs (Livewire uploads) are generated with https://APP_URL.
 * Mark requests as HTTPS so signature validation matches.
 */
class TrustCapRoverTlsTermination
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('production')) {
            $request->server->set('HTTPS', 'on');
            $request->server->set('SERVER_PORT', '443');
        }

        return $next($request);
    }
}
