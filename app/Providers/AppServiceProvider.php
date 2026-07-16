<?php

namespace App\Providers;

use App\Filament\Support\FluentWidgetConfiguration;
use App\Listeners\RecordUserLastLogin;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WidgetConfiguration::class, FluentWidgetConfiguration::class);
    }

    public function boot(): void
    {
        Event::listen(Login::class, RecordUserLastLogin::class);

        // CapRover terminates TLS at the proxy; force https URLs in production to avoid mixed content.
        if (config('app.env') === 'production') {
            if ($rootUrl = config('app.url')) {
                URL::forceRootUrl($rootUrl);
            }

            URL::forceScheme('https');
        }

        \Illuminate\Support\Carbon::macro('jalali', function (?string $format = null) {
            /** @var \Illuminate\Support\Carbon $this */
            return \App\Support\JalaliDate::format($this, $format ?? \App\Support\JalaliDate::DATE);
        });
    }
}
