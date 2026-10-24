<?php

namespace App\Filament\Widgets\PlatformAnalytics;

use App\Filament\Widgets\PlatformAnalytics\Concerns\FormatsPlatformMetrics;
use App\Services\PlatformAnalytics\PlatformMetrics;
use App\Support\JalaliDate;
use Filament\Widgets\ChartWidget;

class ActiveOrganizationsChart extends ChartWidget
{
    use FormatsPlatformMetrics;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

    public function getHeading(): string
    {
        return __('filament.platform_analytics.monthly_chart_heading');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $series = app(PlatformMetrics::class)->monthlySeries(6);

        return [
            'datasets' => [
                [
                    'label' => __('filament.platform_analytics.active_organizations'),
                    'data' => array_column($series, 'active'),
                    'backgroundColor' => '#f59e0b',
                ],
                [
                    'label' => __('filament.platform_analytics.new_organizations'),
                    'data' => array_column($series, 'new'),
                    'backgroundColor' => '#3b82f6',
                ],
                [
                    'label' => __('filament.platform_analytics.reactivated'),
                    'data' => array_column($series, 'reactivated'),
                    'backgroundColor' => '#10b981',
                ],
                [
                    'label' => __('filament.platform_analytics.churned'),
                    'data' => array_column($series, 'churned'),
                    'backgroundColor' => '#ef4444',
                ],
            ],
            'labels' => array_map(fn (array $month): string => JalaliDate::persianDigits($month['label']), $series),
        ];
    }
}
