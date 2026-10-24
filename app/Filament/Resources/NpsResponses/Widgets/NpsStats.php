<?php

namespace App\Filament\Resources\NpsResponses\Widgets;

use App\Filament\Widgets\PlatformAnalytics\Concerns\FormatsPlatformMetrics;
use App\Services\PlatformAnalytics\NpsMetrics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NpsStats extends StatsOverviewWidget
{
    use FormatsPlatformMetrics;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $nps = app(NpsMetrics::class)->summary();
        $trend = $nps['score'] !== null && $nps['previous_score'] !== null ? $nps['score'] - $nps['previous_score'] : null;

        return [
            Stat::make(__('admin.nps.score'), $nps['score'] === null ? '—' : $this->number($nps['score']))
                ->description($trend === null
                    ? __('admin.nps.window', ['days' => $this->number(NpsMetrics::WINDOW_DAYS)])
                    : __('admin.nps.trend', ['value' => $this->number($trend)]))
                ->color($nps['score'] === null ? 'gray' : ($nps['score'] >= 30 ? 'success' : ($nps['score'] >= 0 ? 'warning' : 'danger'))),
            Stat::make(__('admin.nps.promoters'), $this->number($nps['promoters']))->color('success'),
            Stat::make(__('admin.nps.passives'), $this->number($nps['passives']))->color('gray'),
            Stat::make(__('admin.nps.detractors'), $this->number($nps['detractors']))->color('danger'),
            Stat::make(__('admin.nps.response_rate'), $this->percent($nps['response_rate']))
                ->description(__('admin.nps.responses_count', ['count' => $this->number($nps['responses']), 'skipped' => $this->number($nps['skipped'])])),
        ];
    }
}
