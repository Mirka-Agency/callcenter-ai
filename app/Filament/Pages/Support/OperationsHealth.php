<?php

namespace App\Filament\Pages\Support;

use App\Domain\Platform\Enums\AdminArea;
use App\Filament\Concerns\RequiresAdminArea;
use App\Services\PlatformAnalytics\OperationsMetrics;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class OperationsHealth extends Page
{
    use RequiresAdminArea;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.support.operations-health';

    public string $window = '24h';

    public static function adminArea(): AdminArea
    {
        return AdminArea::Support;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.operations_health');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.groups.support');
    }

    public function getTitle(): string
    {
        return __('admin.operations.title');
    }

    public function setWindow(string $window): void
    {
        $this->window = array_key_exists($window, self::windows()) ? $window : '24h';
    }

    /** @return array<string, int> hours per window */
    public static function windows(): array
    {
        return ['1h' => 1, '24h' => 24, '7d' => 168, '30d' => 720];
    }

    public function getViewData(): array
    {
        $metrics = app(OperationsMetrics::class);
        $since = now()->subHours(self::windows()[$this->window] ?? 24);
        $aiSince = now()->subDays(30);

        return [
            'providers' => $metrics->providerHealth($since),
            'failingRate' => OperationsMetrics::FAILING_RATE,
            'aiQuality' => $metrics->aiQuality($aiSince),
            'llmRequests' => $metrics->llmRequests($aiSince),
            'topErrors' => $metrics->topProcessingErrors($since),
            'stuckCalls' => $metrics->stuckCalls(),
        ];
    }
}
