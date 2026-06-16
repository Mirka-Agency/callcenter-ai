<?php

namespace App\Support\Onboarding;

use App\Support\Navigation\EmployerNavigation;
use Illuminate\Support\Facades\Route;

class EmployerOnboarding
{
    /** @return array<string, string> */
    public static function routeUrls(): array
    {
        $urls = [];

        foreach (EmployerNavigation::items() as $item) {
            if (! empty($item['route']) && Route::has($item['route'])) {
                $urls[$item['route']] = route($item['route']);
            }
        }

        $extras = [
            'employer.employees.create',
        ];

        foreach ($extras as $route) {
            if (Route::has($route)) {
                $urls[$route] = route($route);
            }
        }

        return $urls;
    }
}
