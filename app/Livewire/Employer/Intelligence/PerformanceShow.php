<?php

namespace App\Livewire\Employer\Intelligence;

use App\Enums\ReportDatePreset;
use App\Livewire\Employer\Intelligence\Concerns\HasPerformanceFilters;
use App\Models\OrganizationUser;
use App\Services\EmployerContext;
use App\Services\Performance\EmployeePerformanceAnalytics;
use App\Support\PerformanceReportExporter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employer')]
#[Title('پروفایل عملکرد کارشناس')]
class PerformanceShow extends Component
{
    use HasPerformanceFilters;

    public OrganizationUser $employee;

    public function mount(OrganizationUser $employee): void
    {
        abort_unless($employee->organization_id === EmployerContext::organizationId(), 404);
        $this->employee = $employee->load('user');
        $this->mountPerformanceFilters();
    }

    public function export(string $format)
    {
        $filter = $this->performanceFilter($this->employee->id);

        return match ($format) {
            'csv' => PerformanceReportExporter::downloadEmployeeCsv($filter, $this->employee),
            'xlsx', 'excel' => PerformanceReportExporter::downloadEmployeeExcel($filter, $this->employee),
            'pdf' => PerformanceReportExporter::downloadEmployeePdf($filter, $this->employee),
            default => null,
        };
    }

    public function render()
    {
        $filter = $this->performanceFilter($this->employee->id);
        $profile = app(EmployeePerformanceAnalytics::class)->employeeProfile($filter, $this->employee);

        $employees = OrganizationUser::query()
            ->where('organization_id', EmployerContext::organizationId())
            ->where('is_active', true)
            ->with('user:id,avatar_path,name')
            ->orderBy('first_name')
            ->get(['id', 'user_id', 'first_name', 'last_name', 'department']);

        return view('livewire.employer.intelligence.performance-show', [
            'profile' => $profile,
            'filter' => $filter,
            'filterEmployees' => $employees,
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
