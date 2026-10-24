<?php

namespace App\Filament\Pages\Finance;

use App\Domain\Platform\Enums\AdminArea;
use App\Filament\Concerns\RequiresAdminArea;
use App\Services\Billing\BillingMetrics;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class FinanceOverview extends Page
{
    use RequiresAdminArea;

    public const MONTHS = 6;

    protected static ?string $slug = 'finance-overview';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartPie;

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.finance.finance-overview';

    public static function adminArea(): AdminArea
    {
        return AdminArea::Finance;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.finance_overview');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.groups.finance');
    }

    public function getTitle(): string
    {
        return __('admin.navigation.finance_overview');
    }

    public function getViewData(): array
    {
        $metrics = app(BillingMetrics::class);

        return [
            'overview' => $metrics->overview(),
            'movement' => array_reverse($metrics->mrrMovement(self::MONTHS)),
            'aging' => $metrics->receivablesAging(),
            'margins' => $metrics->margins(),
            'quota' => $metrics->quotaUsage(),
            'acquisition' => array_reverse($metrics->acquisition(self::MONTHS)),
        ];
    }
}
