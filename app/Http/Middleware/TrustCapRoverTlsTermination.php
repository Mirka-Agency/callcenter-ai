<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When TLS is terminated in front of the container (CapRover), PHP only sees HTTP,
 * but signed URLs are generated with https://APP_URL. Mark those requests as HTTPS
 * so signature validation matches.
 *
 * Skipped when APP_URL is plain http (typical on-prem LAN) so session cookies work.
 */
class TrustCapRoverTlsTermination
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.force_https')) {
            $request->server->set('HTTPS', 'on');
            $request->server->set('SERVER_PORT', '443');
        }

        return $next($request);
    }
}
