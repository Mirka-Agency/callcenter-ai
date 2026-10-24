<?php

namespace App\Filament\Widgets\PlatformAnalytics;

use App\Filament\Pages\Analytics\BusinessAnalytics;
use App\Filament\Widgets\PlatformAnalytics\Concerns\FormatsPlatformMetrics;
use App\Services\PlatformAnalytics\PlatformMetrics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformPulseStats extends StatsOverviewWidget
{
    use FormatsPlatformMetrics;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getHeading(): ?string
    {
        return __('filament.platform_analytics.pulse_heading');
    }

    protected function getStats(): array
    {
        $metrics = app(PlatformMetrics::class);
        [$lastMonth, $thisMonth] = array_slice($metrics->monthlySeries(2), -2);
        $processing = $metrics->processing(now()->subDays(30), now());
        $thisMonthCalls = $metrics->processing($thisMonth['start'], $thisMonth['end']);

        $churnRate = $lastMonth['churn_rate'];
        $churnColor = match (true) {
            $churnRate === null => 'gray',
            $churnRate > 0.1 => 'danger',
            $churnRate > 0.05 => 'warning',
            default => 'success',
        };

        $successRate = $processing['success_rate'];

        return [
            Stat::make(__('filament.platform_analytics.active_organizations'), $this->number($thisMonth['active']))
                ->description(__('filament.platform_analytics.previous_month_value', ['value' => $this->number($lastMonth['active'])]))
                ->color($thisMonth['active'] >= $lastMonth['active'] ? 'success' : 'warning')
                ->url(BusinessAnalytics::getUrl()),
            Stat::make(__('filament.platform_analytics.churn_rate_last_month'), $this->percent($churnRate))
                ->description(__('filament.platform_analytics.churned_of_active', [
                    'churned' => $this->number($lastMonth['churned']),
                    'month' => $lastMonth['label'],
                ]))
                ->color($churnColor)
                ->url(BusinessAnalytics::getUrl()),
            Stat::make(__('filament.platform_analytics.calls_analyzed_this_month'), $this->number($thisMonthCalls['analyzed']))
                ->description(__('filament.platform_analytics.calls_received', ['value' => $this->number($thisMonthCalls['total'])]))
                ->color('info'),
            Stat::make(__('filament.platform_analytics.processing_success_rate'), $this->percent($successRate))
                ->description(__('filament.platform_analytics.failed_calls_30_days', ['value' => $this->number($processing['failed'])]))
                ->color(match (true) {
                    $successRate === null => 'gray',
                    $successRate < 0.9 => 'danger',
                    $successRate < 0.97 => 'warning',
                    default => 'success',
                }),
        ];
    }
}
