<?php

namespace App\Livewire\Employer\Dashboard;

use App\DTOs\ReportFilter;
use App\Enums\ReportDatePreset;
use App\Livewire\Employer\Concerns\HasAgentPerformanceCardFeed;
use App\Services\EmployerContext;
use App\Services\EmployerDashboardAnalytics;
use App\Services\Performance\EmployeePerformanceAnalytics;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employer')]
#[Title('داشبورد مدیر')]
class Overview extends Component
{
    use HasAgentPerformanceCardFeed;

    public function render()
    {
        $organization = EmployerContext::organization();
        $organizationId = $organization->id;
        $analytics = EmployerDashboardAnalytics::forOrganization($organizationId);

        $performanceFilter = ReportFilter::make(
            organizationId: $organizationId,
            preset: ReportDatePreset::Last30,
        );
        $performanceDashboard = app(EmployeePerformanceAnalytics::class)->teamDashboard($performanceFilter);

        $agents = $performanceDashboard['employees'];

        return view('livewire.employer.dashboard.overview', [
            'organization' => $organization,
            'cockpit' => $analytics->cockpit(),
            'agentCardFeed' => $this->agentCardFeed($agents),
            'teamKpis' => $performanceDashboard['kpis'],
            'teamWeaknesses' => $performanceDashboard['team_weaknesses'],
            'qualityTrend' => $performanceDashboard['quality_trend'],
            'dailyTrend' => $analytics->dailyTrend(),
            'activityFeed' => $analytics->activityFeed(6),
        ]);
    }
}
