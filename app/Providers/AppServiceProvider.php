<?php

namespace App\Providers;

use App\Application\Call\Services\CallEmployeeResolver;
use App\Filament\Support\FluentWidgetConfiguration;
use App\Listeners\RecordUserLastLogin;
use App\Services\Reports\ChartHolidayCalendar;
use App\Services\Reports\OrganizationCallMetrics;
use App\Support\JalaliDate;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
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
    }

    public function boot(): void
    {
        Event::listen(Login::class, RecordUserLastLogin::class);

        $this->ignoreViteHotFileOutsideLocal();

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

    /**
     * Vite writes public/hot during `npm run dev`. If that marker reaches
     * production/on-prem, @vite points browsers at :5173 and charts, audio
     * playback, and Livewire clicks all die. Docker already strips the file;
     * this is the app-level last line of defense.
     */
    private function ignoreViteHotFileOutsideLocal(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        // Point @vite at a non-existent marker so public/hot is ignored even if
        // a deploy/rsync left the file on disk. Do this for testing/staging too.
        Vite::useHotFile(storage_path('framework/vite-hot-disabled'));

        if (! $this->app->isProduction()) {
            return;
        }

        $hotPath = public_path('hot');

        if (! is_file($hotPath)) {
            return;
        }

        Log::warning('vite_hot_file_removed_in_production', [
            'path' => $hotPath,
        ]);
        @unlink($hotPath);
    }
}
