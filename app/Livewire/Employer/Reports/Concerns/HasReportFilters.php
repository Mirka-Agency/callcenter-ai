<?php

namespace App\Livewire\Employer\Reports\Concerns;

use App\DTOs\ReportFilter;
use App\Enums\ReportDatePreset;
use App\Services\EmployerContext;
use Carbon\Carbon;
use Livewire\Attributes\Url;

trait HasReportFilters
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

    #[Url(as: 'compare')]
    public bool $compareMode = false;

    public bool $showMoreDatePresets = false;

    public bool $showCustomDateRange = false;

    public ?string $draftCustomFrom = null;

    public ?string $draftCustomTo = null;

    public function mountHasReportFilters(): void
    {
        $this->showCustomDateRange = $this->datePreset === ReportDatePreset::Custom->value;
        $this->draftCustomFrom = $this->customFrom;
        $this->draftCustomTo = $this->customTo;
    }

    public function updatedDatePreset(): void
    {
        if ($this->datePreset !== ReportDatePreset::Custom->value) {
            $this->customFrom = null;
            $this->customTo = null;
            $this->showCustomDateRange = false;
        }
    }

    public function updatedSelectedEmployeeIds(): void
    {
        $this->selectedEmployeeIds = array_values(array_filter(
            array_map('intval', $this->selectedEmployeeIds),
        ));
    }

    public function setDatePreset(string $preset): void
    {
        $this->datePreset = $preset;

        if ($preset !== ReportDatePreset::Custom->value) {
            $this->customFrom = null;
            $this->customTo = null;
            $this->draftCustomFrom = null;
            $this->draftCustomTo = null;
            $this->showCustomDateRange = false;
        } else {
            $this->showCustomDateRange = true;
            $this->draftCustomFrom = $this->customFrom;
            $this->draftCustomTo = $this->customTo;
        }
    }

    public function toggleCustomDateRange(): void
    {
        $this->showCustomDateRange = ! $this->showCustomDateRange;

        if ($this->showCustomDateRange) {
            $this->draftCustomFrom = $this->customFrom;
            $this->draftCustomTo = $this->customTo;
        } elseif ($this->datePreset === ReportDatePreset::Custom->value) {
            $this->setDatePreset(ReportDatePreset::Last30->value);
        }
    }

    public function applyCustomDateRange(?string $from = null, ?string $to = null): void
    {
        if ($from !== null || $to !== null) {
            $this->draftCustomFrom = $from ?: null;
            $this->draftCustomTo = $to ?: null;
        }

        $this->customFrom = $this->draftCustomFrom;
        $this->customTo = $this->draftCustomTo;
        $this->datePreset = ReportDatePreset::Custom->value;
        $this->showCustomDateRange = true;
    }

    public function toggleMoreDatePresets(): void
    {
        $this->showMoreDatePresets = ! $this->showMoreDatePresets;
    }

    public function clearDateFilter(): void
    {
        $this->setDatePreset(ReportDatePreset::Last30->value);
        $this->draftCustomFrom = null;
        $this->draftCustomTo = null;
    }

    public function clearEmployeeFilter(): void
    {
        $this->selectedEmployeeIds = [];
        $this->compareMode = false;
    }

    public function clearFilters(): void
    {
        $this->setDatePreset(ReportDatePreset::Last30->value);
        $this->clearEmployeeFilter();
        $this->showMoreDatePresets = false;
    }

    public function toggleEmployee(int $employeeId): void
    {
        if (in_array($employeeId, $this->selectedEmployeeIds, true)) {
            $this->selectedEmployeeIds = array_values(array_diff($this->selectedEmployeeIds, [$employeeId]));
        } else {
            $this->selectedEmployeeIds[] = $employeeId;
        }
    }

    protected function reportFilter(): ReportFilter
    {
        $preset = ReportDatePreset::tryFrom($this->datePreset) ?? ReportDatePreset::Last30;

        return ReportFilter::make(
            organizationId: EmployerContext::organizationId(),
            preset: $preset,
            customFrom: $this->customFrom ? Carbon::parse($this->customFrom) : null,
            customTo: $this->customTo ? Carbon::parse($this->customTo) : null,
            employeeIds: array_map('intval', $this->selectedEmployeeIds),
            compareMode: $this->compareMode,
        );
    }
}
