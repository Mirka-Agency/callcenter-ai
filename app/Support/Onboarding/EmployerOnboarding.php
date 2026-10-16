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
            $entries = array_merge([$item], $item['children'] ?? []);

            foreach ($entries as $entry) {
                if (! empty($entry['route']) && Route::has($entry['route'])) {
                    $urls[$entry['route']] = route($entry['route']);
                }
            }
        }

        $extras = [
            'employer.employees.create',
            'employer.customers.companies.index',
            'employer.customers.contacts.index',
        ];

        foreach ($extras as $route) {
            if (Route::has($route)) {
                $urls[$route] = route($route);
            }
        }

        return $urls;
    }
}
