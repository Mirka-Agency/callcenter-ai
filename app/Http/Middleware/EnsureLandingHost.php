<?php

namespace App\Http\Middleware;

use App\Support\LandingHost;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLandingHost
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(LandingHost::matches($request), 404);

        return $next($request);
    }
}
