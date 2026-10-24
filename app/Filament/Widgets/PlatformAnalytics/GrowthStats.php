<?php

namespace App\Filament\Widgets\PlatformAnalytics;

use App\Filament\Pages\Analytics\BusinessAnalytics;
use App\Filament\Widgets\PlatformAnalytics\Concerns\FormatsPlatformMetrics;
use App\Services\PlatformAnalytics\PlatformMetrics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GrowthStats extends StatsOverviewWidget
{
    use FormatsPlatformMetrics;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getHeading(): ?string
    {
        return __('filament.platform_analytics.growth_heading');
    }

    protected function getStats(): array
    {
        $metrics = app(PlatformMetrics::class);
        [$lastMonth, $thisMonth] = array_slice($metrics->monthlySeries(2), -2);
        $activation = $metrics->activation();

        return [
            Stat::make(__('filament.platform_analytics.new_organizations_this_month'), $this->number($thisMonth['new']))
                ->description(__('filament.platform_analytics.previous_month_value', ['value' => $this->number($lastMonth['new'])]))
                ->color('primary'),
            Stat::make(__('filament.platform_analytics.reactivated_this_month'), $this->number($thisMonth['reactivated']))
                ->description(__('filament.platform_analytics.reactivated_description'))
                ->color('success'),
            Stat::make(__('filament.platform_analytics.activation_rate'), $this->percent($activation['rate']))
                ->description(__('filament.platform_analytics.activation_description', [
                    'activated' => $this->number($activation['activated']),
                    'eligible' => $this->number($activation['eligible']),
                    'days' => $this->number(PlatformMetrics::ACTIVATION_DAYS),
                ]))
                ->color('info')
                ->url(BusinessAnalytics::getUrl()),
            Stat::make(
                __('filament.platform_analytics.time_to_first_value'),
                $activation['median_days_to_first_value'] === null
                    ? '—'
                    : __('filament.platform_analytics.days_value', ['value' => $this->number($activation['median_days_to_first_value'], 1)]),
            )
                ->description(__('filament.platform_analytics.time_to_first_value_description'))
                ->color('gray'),
        ];
    }
}
