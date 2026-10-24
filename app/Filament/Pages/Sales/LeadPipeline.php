<?php

namespace App\Filament\Pages\Sales;

use App\Domain\Platform\Enums\AdminArea;
use App\Domain\Platform\Enums\LeadStatus;
use App\Filament\Concerns\RequiresAdminArea;
use App\Models\DemoRequest;
use App\Services\Sales\LeadPipelineMetrics;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class LeadPipeline extends Page
{
    use RequiresAdminArea;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFunnel;

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.sales.lead-pipeline';

    public static function adminArea(): AdminArea
    {
        return AdminArea::Sales;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.leads');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.groups.sales');
    }

    public function getTitle(): string
    {
        return __('admin.leads.pipeline_title');
    }

    public function getViewData(): array
    {
        $metrics = app(LeadPipelineMetrics::class);

        return [
            'board' => $metrics->statusBoard(),
            'statuses' => LeadStatus::cases(),
            'types' => LeadPipelineMetrics::types(),
            'sources' => $metrics->sources(),
            'response' => $metrics->responseTimes(),
            'followUps' => $metrics->followUps(),
            'slaHours' => DemoRequest::SLA_HOURS,
        ];
    }
}
