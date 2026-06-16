<?php

namespace App\Livewire\Employee\Performance;

use App\DTOs\ReportFilter;
use App\Enums\ReportDatePreset;
use App\Services\EmployeeContext;
use App\Services\EmployeeDashboardAnalytics;
use App\Services\Performance\EmployeePerformanceAnalytics;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.employee')]
#[Title('عملکرد من')]
class Index extends Component
{
    #[Url(as: 'preset')]
    public string $period = 'last_30';

    public function setPeriod(string $preset): void
    {
        $this->period = $preset;
    }

    public function render()
    {
        $membership = EmployeeContext::membership()->load('user');
        $preset = ReportDatePreset::tryFrom($this->period) ?? ReportDatePreset::Last30;
        $filter = ReportFilter::make(
            organizationId: EmployeeContext::organizationId(),
            preset: $preset,
            employeeIds: [$membership->id],
        );

        $profile = app(EmployeePerformanceAnalytics::class)->employeeProfile($filter, $membership);
        $dashboard = EmployeeDashboardAnalytics::forEmployee($membership);

        return view('livewire.employee.performance.index', [
            'membership' => $membership,
            'profile' => $profile,
            'achievements' => $dashboard->achievements(),
            'recommendations' => $dashboard->recommendations(),
            'periodPresets' => [
                ReportDatePreset::Last7,
                ReportDatePreset::Last30,
                ReportDatePreset::ThisMonth,
                ReportDatePreset::CurrentQuarter,
            ],
            'activePreset' => $preset,
        ]);
    }
}
