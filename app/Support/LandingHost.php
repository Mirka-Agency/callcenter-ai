<?php

namespace App\Support;

use Illuminate\Http\Request;

final class LandingHost
{
    public static function matches(Request $request): bool
    {
        if (app()->environment('local')) {
            return true;
        }

        $host = strtolower($request->getHost());

        return in_array($host, config('app.landing_hosts', []), true);
    }
}
