<?php

namespace App\Livewire\Employer\Intelligence;

use App\Enums\ReportDatePreset;
use App\Livewire\Employer\Concerns\HasAgentPerformanceCardFeed;
use App\Livewire\Employer\Concerns\HasTeamWeaknessDrilldown;
use App\Livewire\Employer\Intelligence\Concerns\HasPerformanceFilters;
use App\Models\OrganizationUser;
use App\Services\EmployerContext;
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

        $employees = OrganizationUser::query()
            ->where('organization_id', EmployerContext::organizationId())
            ->where('is_active', true)
            ->with('user:id,avatar_path,name')
            ->orderBy('first_name')
            ->get(['id', 'user_id', 'first_name', 'last_name', 'department']);

        return view('livewire.employer.intelligence.performance', [
            'dashboard' => $dashboard,
            'selectedTeamWeakness' => $selectedWeakness,
            'teamWeaknessCalls' => $selectedWeakness
                ? $performance->teamWeaknessCalls($filter, $selectedWeakness)
                : [],
            'agentCardFeed' => $this->agentCardFeed($dashboard['employees']),
            'filterEmployees' => $employees,
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
