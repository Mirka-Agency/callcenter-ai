<?php

namespace App\Livewire\Employer\Intelligence\Concerns;

use App\DTOs\ReportFilter;
use App\Enums\ReportDatePreset;
use App\Services\EmployerContext;
use Carbon\Carbon;
use Livewire\Attributes\Url;

trait HasPerformanceFilters
{
    #[Url(as: 'preset')]
    public string $datePreset = 'last_30';

    #[Url(as: 'from')]
    public ?string $customFrom = null;

    #[Url(as: 'to')]
    public ?string $customTo = null;

    /** @var list<int|string> */
    #[Url(as: 'employees')]
    public array $selectedEmployeeIds = [];

    public function updatedCustomFrom(): void
    {
        $this->datePreset = ReportDatePreset::Custom->value;
    }

    public function updatedDatePreset(): void
    {
        if ($this->datePreset !== ReportDatePreset::Custom->value) {
            $this->customFrom = null;
            $this->customTo = null;
        }
    }

    public function updatedCustomTo(): void
    {
        $this->datePreset = ReportDatePreset::Custom->value;
    }

    public function updatedSelectedEmployeeIds(): void
    {
        $this->selectedEmployeeIds = array_values(array_filter(
            array_map('intval', $this->selectedEmployeeIds),
        ));
    }

    public function clearEmployeeFilter(): void
    {
        $this->selectedEmployeeIds = [];
    }

    protected function performanceFilter(?int $employeeId = null): ReportFilter
    {
        $preset = ReportDatePreset::tryFrom($this->datePreset) ?? ReportDatePreset::Last30;
        $employeeIds = $employeeId !== null
            ? [$employeeId]
            : array_map('intval', $this->selectedEmployeeIds);

        return ReportFilter::make(
            organizationId: EmployerContext::organizationId(),
            preset: $preset,
            customFrom: $this->customFrom ? Carbon::parse($this->customFrom) : null,
            customTo: $this->customTo ? Carbon::parse($this->customTo) : null,
            employeeIds: $employeeIds,
        );
    }
}
