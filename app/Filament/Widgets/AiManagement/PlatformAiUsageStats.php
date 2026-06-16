<?php

namespace App\Filament\Widgets\AiManagement;

use App\Enums\UserRole;
use App\Models\PlatformAiSettings;
use App\Services\AiUsageAnalyticsService;
use App\Support\PersianNumber;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformAiUsageStats extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->role === UserRole::SuperAdmin;
    }

    protected function getStats(): array
    {
        $overview = app(AiUsageAnalyticsService::class)->platformOverview();

        return [
            Stat::make(__('filament.widgets.input_tokens'), PersianNumber::format($overview['input_tokens'], 0))
                ->description(__('filament.widgets.platform_wide'))
                ->color('info'),
            Stat::make(__('filament.widgets.output_tokens'), PersianNumber::format($overview['output_tokens'], 0))
                ->color('info'),
            Stat::make(__('filament.widgets.total_tokens'), PersianNumber::format($overview['total_tokens'], 0))
                ->color('warning'),
            Stat::make(__('filament.widgets.total_ai_cost'), PlatformAiSettings::formatMoney($overview['total_cost']))
                ->color('success'),
            Stat::make(__('filament.widgets.analyses'), PersianNumber::format($overview['analyses_count'], 0))
                ->description(__('filament.widgets.organizations_count', ['count' => $overview['organizations_count']]))
                ->color('primary'),
        ];
    }
}
