<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Support\LandingHost;
use App\Support\Seo\SeoSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    /** The app (customer panel) host must never be indexed, whatever the settings say. */
    public const APP_HOST_ROBOTS = "User-agent: *\nDisallow: /\n";

    public function __invoke(Request $request): Response
    {
        return response(
            LandingHost::matches($request) ? SeoSettings::robotsTxt() : self::APP_HOST_ROBOTS,
            200,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }
}
