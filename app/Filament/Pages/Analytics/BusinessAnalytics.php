<?php

namespace App\Filament\Pages\Analytics;

use App\Domain\Platform\Enums\AdminArea;
use App\Filament\Concerns\RequiresAdminArea;
use App\Services\PlatformAnalytics\EngagementMetrics;
use App\Services\PlatformAnalytics\PlatformMetrics;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class BusinessAnalytics extends Page
{
    use RequiresAdminArea;

    public const MONTHS = 12;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.analytics.business-analytics';

    public static function adminArea(): AdminArea
    {
        return AdminArea::Analytics;
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.business_analytics');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.groups.analytics');
    }

    public function getTitle(): string
    {
        return __('filament.pages.business_analytics');
    }

    public function getViewData(): array
    {
        $metrics = app(PlatformMetrics::class);
        $engagement = app(EngagementMetrics::class);

        return [
            'series' => array_reverse($metrics->monthlySeries(self::MONTHS)),
            'cohorts' => array_reverse($metrics->cohorts(self::MONTHS)),
            'activation' => $metrics->activation(),
            'activationDays' => PlatformMetrics::ACTIVATION_DAYS,
            'adoption' => $engagement->featureAdoption(),
            'expansion' => $engagement->expansion(),
            'onboarding' => $engagement->onboardingFunnel(),
            'activeUsers' => $engagement->activeUsers(),
        ];
    }
}
