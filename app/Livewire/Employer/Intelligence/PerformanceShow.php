<?php

namespace App\Livewire\Employer\Intelligence;

use App\Enums\ReportDatePreset;
use App\Livewire\Employer\Intelligence\Concerns\HasPerformanceFilters;
use App\Models\OrganizationUser;
use App\Services\EmployerContext;
use App\Services\Performance\EmployeePerformanceAnalytics;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employer')]
#[Title('پروفایل عملکرد کارشناس')]
class PerformanceShow extends Component
{
    use HasPerformanceFilters;

    public OrganizationUser $employee;

    public ?string $printDraftFrom = null;

    public ?string $printDraftTo = null;

    public bool $showPrintDateRange = false;

    public function mount(OrganizationUser $employee): void
    {
        abort_unless($employee->organization_id === EmployerContext::organizationId(), 404);
        $this->employee = $employee->load('user');
        $this->mountPerformanceFilters();
        $this->syncPrintDraftsFromFilter();
    }

    public function openPrintDateRange(): void
    {
        $this->syncPrintDraftsFromFilter();
        $this->showPrintDateRange = true;
    }

    public function closePrintDateRange(): void
    {
        $this->showPrintDateRange = false;
    }

    public function applyPrintDateRange(?string $from = null, ?string $to = null): bool
    {
        if (! $this->isValidPrintDateRange($from, $to)) {
            return false;
        }

        $this->showPrintDateRange = false;
        $this->applyCustomDateRange($from, $to);

        return true;
    }

    protected function syncPrintDraftsFromFilter(): void
    {
        $filter = $this->performanceFilter($this->employee->id);

        $this->printDraftFrom = $filter->from->toDateString();
        $this->printDraftTo = $filter->to->toDateString();
    }

    protected function isValidPrintDateRange(?string $from, ?string $to): bool
    {
        if (! is_string($from) || ! is_string($to)) {
            return false;
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            return false;
        }

        try {
            $fromDate = Carbon::createFromFormat('Y-m-d', $from);
            $toDate = Carbon::createFromFormat('Y-m-d', $to);
        } catch (\Throwable) {
            return false;
        }

        if ($fromDate === false || $toDate === false || $fromDate->format('Y-m-d') !== $from || $toDate->format('Y-m-d') !== $to) {
            return false;
        }

        return $fromDate->lte($toDate);
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
