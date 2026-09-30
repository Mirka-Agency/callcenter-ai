<?php

namespace App\Livewire\Employer\Intelligence;

use App\Livewire\Employer\Concerns\HasAgentPerformanceCardFeed;
use App\Livewire\Employer\Concerns\HasQualityTrendDrilldown;
use App\Livewire\Employer\Concerns\HasTeamWeaknessDrilldown;
use App\Livewire\Employer\Intelligence\Concerns\HasPerformanceFilters;
use App\Services\Performance\EmployeePerformanceAnalytics;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employer')]
#[Title('عملکرد کارشناسان')]
class Performance extends Component
{
    use HasAgentPerformanceCardFeed;
    use HasPerformanceFilters;
    use HasQualityTrendDrilldown;
    use HasTeamWeaknessDrilldown;

    public function mount(): void
    {
        $this->mountPerformanceFilters();
    }

    public function render()
    {
        $filter = $this->performanceFilter();
        $performance = app(EmployeePerformanceAnalytics::class);
        $dashboard = $performance->teamDashboard($filter);
        $selectedWeakness = $this->resolvedTeamWeakness($dashboard['team_weaknesses']);
        $selectedQualityPeriod = $this->resolvedQualityTrendPeriod($dashboard['quality_trend']);

        return view('livewire.employer.intelligence.performance', [
            'dashboard' => $dashboard,
            'selectedTeamWeakness' => $selectedWeakness,
            'teamWeaknessCalls' => $selectedWeakness
                ? $performance->teamWeaknessCalls($filter, $selectedWeakness)
                : [],
            'agentCardFeed' => $this->agentCardFeed($dashboard['employees']),
            'filter' => $filter,
            'qualityTrendInsights' => $dashboard['quality_trend_insights'] ?? [],
            'qualityTrendInsight' => $selectedQualityPeriod
                ? ($dashboard['quality_trend_insights'][$selectedQualityPeriod] ?? null)
                : null,
            'agentProfileBase' => preg_replace('#/\d+$#', '', route('employer.intelligence.performance.show', 1)),
            'agentProfileQuery' => http_build_query(array_filter([
                'preset' => $this->datePreset,
                'from' => $this->customFrom,
                'to' => $this->customTo,
            ], fn ($value) => $value !== null && $value !== '')),
        ]);
    }
}
