<?php

namespace App\Providers;

use App\Application\Call\Services\CallEmployeeResolver;
use App\Filament\Support\FluentWidgetConfiguration;
use App\Listeners\RecordUserLastLogin;
use App\Services\Performance\Data\PerformanceDataLoader;
use App\Services\Reports\ChartHolidayCalendar;
use App\Services\Reports\OrganizationCallMetrics;
use App\Support\JalaliDate;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WidgetConfiguration::class, FluentWidgetConfiguration::class);
        // Chart pages ask for the same extension calendar many times per request.
        // Keep one instance so that work is not repeated for every sidebar page.
        $this->app->scoped(CallEmployeeResolver::class);
        $this->app->scoped(OrganizationCallMetrics::class);
        $this->app->scoped(ChartHolidayCalendar::class);
        $this->app->scoped(PerformanceDataLoader::class);
    }

    public function boot(): void
    {
        Event::listen(Login::class, RecordUserLastLogin::class);

        // CapRover/cloud: APP_URL is https://… — force https URLs.
        // On-prem LAN HTTP: APP_URL is http://… — do not force https (breaks cookies/CSRF).
        if (config('app.force_https')) {
            if ($rootUrl = config('app.url')) {
                URL::forceRootUrl($rootUrl);
            }

            URL::forceScheme('https');
        }

        Carbon::macro('jalali', function (?string $format = null) {
            /** @var Carbon $this */
            return JalaliDate::format($this, $format ?? JalaliDate::DATE);
        });
    }
}
