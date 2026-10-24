<?php

namespace App\Filament\Widgets\PlatformAnalytics;

use App\Filament\Pages\Analytics\BusinessAnalytics;
use App\Filament\Widgets\PlatformAnalytics\Concerns\FormatsPlatformMetrics;
use App\Services\PlatformAnalytics\EngagementMetrics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EngagementStats extends StatsOverviewWidget
{
    use FormatsPlatformMetrics;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected function getHeading(): ?string
    {
        return __('admin.engagement.heading');
    }

    protected function getStats(): array
    {
        $engagement = app(EngagementMetrics::class);
        $users = $engagement->activeUsers();
        $expansion = $engagement->expansion();

        return [
            Stat::make(__('admin.engagement.dau'), $this->number($users['dau']))
                ->description(__('admin.engagement.wau_value', ['value' => $this->number($users['wau'])]))
                ->color('info'),
            Stat::make(__('admin.engagement.mau'), $this->number($users['mau']))
                ->description(__('admin.engagement.mau_by_role', [
                    'employers' => $this->number($users['by_role']['employer']),
                    'employees' => $this->number($users['by_role']['employee']),
                ]))
                ->color('primary'),
            Stat::make(__('admin.engagement.stickiness'), $this->percent($users['stickiness']))
                ->description(__('admin.engagement.stickiness_description'))
                ->color('gray')
                ->url(BusinessAnalytics::getUrl()),
            Stat::make(__('admin.engagement.expansion'), $this->number($expansion['organizations']))
                ->description(__('admin.engagement.seats_added', ['value' => $this->number($expansion['seats_added'])]))
                ->color('success'),
        ];
    }
}
