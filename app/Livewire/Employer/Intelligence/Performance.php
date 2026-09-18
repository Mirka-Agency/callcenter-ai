<?php

namespace App\Livewire\Employer\Intelligence;

use App\Enums\ReportDatePreset;
use App\Livewire\Employer\Concerns\HasAgentPerformanceCardFeed;
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

        return view('livewire.employer.intelligence.performance', [
            'dashboard' => $dashboard,
            'selectedTeamWeakness' => $selectedWeakness,
            'teamWeaknessCalls' => $selectedWeakness
                ? $performance->teamWeaknessCalls($filter, $selectedWeakness)
                : [],
            'agentCardFeed' => $this->agentCardFeed($dashboard['employees']),
            'filter' => $filter,
            'primaryDatePresets' => [
                ReportDatePreset::Today,
                ReportDatePreset::Yesterday,
                ReportDatePreset::Last7,
                ReportDatePreset::Last30,
                ReportDatePreset::ThisMonth,
            ],
            'moreDatePresets' => [
                ReportDatePreset::PreviousMonth,
                ReportDatePreset::CurrentQuarter,
                ReportDatePreset::CurrentYear,
            ],
        ]);
    }
}
