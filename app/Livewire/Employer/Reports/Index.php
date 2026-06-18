<?php

namespace App\Livewire\Employer\Reports;

use App\Enums\ReportDatePreset;
use App\Livewire\Employer\Reports\Concerns\HasReportFilters;
use App\Models\OrganizationUser;
use App\Services\EmployerContext;
use App\Services\Reports\EmployerReportsAnalytics;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employer')]
#[Title('گزارش‌های مدیریتی')]
class Index extends Component
{
    use HasReportFilters;

    public ?string $drilldownDimension = null;

    public ?string $drilldownValue = null;

    public bool $showDrilldown = false;

    public function mount(): void
    {
        $this->mountHasReportFilters();
    }

    public function drilldown(string $dimension, string $value): void
    {
        $this->drilldownDimension = $dimension;
        $this->drilldownValue = $value;
        $this->showDrilldown = true;
    }

    public function closeDrilldown(): void
    {
        $this->showDrilldown = false;
        $this->drilldownDimension = null;
        $this->drilldownValue = null;
    }

    public function render()
    {
        $filter = $this->reportFilter();
        $analytics = app(EmployerReportsAnalytics::class);
        $dashboard = $analytics->dashboard($filter);

        $drilldownFilter = ($this->showDrilldown && $this->drilldownDimension)
            ? $filter->withDrilldown($this->drilldownDimension, $this->drilldownValue)
            : null;

        $employees = OrganizationUser::query()
            ->where('organization_id', EmployerContext::organizationId())
            ->where('is_active', true)
            ->with('user:id,avatar_path,name')
            ->orderBy('first_name')
            ->get(['id', 'user_id', 'first_name', 'last_name', 'department']);

        return view('livewire.employer.reports.index', [
            'dashboard' => $dashboard,
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
            'filterEmployees' => $employees,
            'employeesById' => $employees->keyBy('id'),
            'filter' => $filter,
            'drilldownAnalyses' => $drilldownFilter
                ? $analytics->drilldownAnalyses($drilldownFilter, 20)
                : null,
        ]);
    }
}
