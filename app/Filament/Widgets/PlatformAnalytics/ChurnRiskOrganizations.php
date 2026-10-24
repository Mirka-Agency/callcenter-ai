<?php

namespace App\Filament\Widgets\PlatformAnalytics;

use App\Domain\Platform\Enums\AdminArea;
use App\Filament\Resources\Organizations\OrganizationResource;
use App\Filament\Widgets\PlatformAnalytics\Concerns\FormatsPlatformMetrics;
use App\Models\Organization;
use App\Services\PlatformAnalytics\OrganizationHealth;
use App\Services\PlatformAnalytics\OrganizationHealthScorer;
use Filament\Widgets\Widget;

class ChurnRiskOrganizations extends Widget
{
    use FormatsPlatformMetrics;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.platform-analytics.churn-risk-organizations';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user?->hasAdminArea(AdminArea::Analytics) === true || $user?->hasAdminArea(AdminArea::Support) === true;
    }

    protected function getViewData(): array
    {
        $risks = app(OrganizationHealthScorer::class)->atRisk(10);
        $titles = Organization::query()
            ->whereKey($risks->pluck('organizationId'))
            ->pluck('title', 'id');

        return [
            'rows' => $risks->map(fn (OrganizationHealth $health): array => [
                'title' => $titles[$health->organizationId] ?? '—',
                'url' => OrganizationResource::getUrl('overview', ['record' => $health->organizationId]),
                'score' => $this->number($health->score),
                'color' => $health->color(),
                'band' => $health->label(),
                'recent' => $this->number($health->analysesLast30Days),
                'previous' => $this->number($health->analysesPrevious30Days),
                'reasons' => $health->reasonLabels(),
            ])->all(),
        ];
    }
}
