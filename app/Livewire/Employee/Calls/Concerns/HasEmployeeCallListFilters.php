<?php

namespace App\Livewire\Employee\Calls\Concerns;

use App\Domain\Voip\Enums\CallStatus;
use App\DTOs\AnalysisListFilter;
use App\Enums\ReportDatePreset;
use App\Services\EmployeeContext;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Url;

trait HasEmployeeCallListFilters
{
    #[Url(as: 'preset')]
    public string $datePreset = 'last_30';

    #[Url(as: 'from')]
    public ?string $customFrom = null;

    #[Url(as: 'to')]
    public ?string $customTo = null;

    #[Url(as: 'status')]
    public ?string $callStatus = null;

    #[Url(as: 'direction')]
    public ?string $directionFilter = null;

    #[Url]
    public ?int $durationMin = null;

    #[Url]
    public ?int $durationMax = null;

    #[Url]
    public string $search = '';

    #[Url(as: 'sort')]
    public string $sortBy = 'analyzed_at';

    #[Url(as: 'dir')]
    public string $sortDir = 'desc';

    public bool $showMoreDatePresets = false;

    public bool $showCustomDateRange = false;

    public ?string $draftCustomFrom = null;

    public ?string $draftCustomTo = null;

    public function mountCallListFilters(): void
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

        $this->resetPage();
    }

    public function updatedCallStatus($value): void
    {
        $this->callStatus = $value ?: null;
        $this->resetPage();
    }

    public function updatedDirectionFilter($value): void
    {
        $this->directionFilter = $value ?: null;
        $this->resetPage();
    }

    public function updatedDurationMin(): void
    {
        $this->resetPage();
    }

    public function updatedDurationMax(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function applyQuickFilter(string $preset): void
    {
        $this->resetPage();

        if ($preset === 'missed') {
            $this->callStatus = CallStatus::Missed->value;

            return;
        }

        if ($preset === 'high_score') {
            $this->clearFilters();
            $this->sortBy = 'score';
            $this->sortDir = 'desc';

            return;
        }

        if ($preset === 'clear') {
            $this->clearFilters();

            return;
        }

        $this->setDatePreset($preset);
    }

    public function setDatePreset(string $preset): void
    {
        $this->resetPage();
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

    public function closeCustomDateRangePanel(): void
    {
        if (! $this->showCustomDateRange) {
            return;
        }

        $this->showCustomDateRange = false;

        if ($this->datePreset === ReportDatePreset::Custom->value) {
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
        $this->resetPage();
    }

    #[Renderless]
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

    public function clearFilters(): void
    {
        $this->datePreset = ReportDatePreset::Last30->value;
        $this->customFrom = null;
        $this->customTo = null;
        $this->draftCustomFrom = null;
        $this->draftCustomTo = null;
        $this->callStatus = null;
        $this->directionFilter = null;
        $this->durationMin = null;
        $this->durationMax = null;
        $this->search = '';
        $this->sortBy = 'analyzed_at';
        $this->sortDir = 'desc';
        $this->showMoreDatePresets = false;
        $this->showCustomDateRange = false;
        $this->resetPage();
    }

    public function sortByColumn(string $column): void
    {
        $allowed = ['analyzed_at', 'duration', 'status', 'score'];

        if (! in_array($column, $allowed, true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = $column === 'analyzed_at' ? 'desc' : 'asc';
        }

        $this->resetPage();
    }

    protected function employeeCallListFilter(): AnalysisListFilter
    {
        $preset = ReportDatePreset::tryFrom($this->datePreset) ?? ReportDatePreset::Last30;

        return AnalysisListFilter::make(
            organizationId: EmployeeContext::organizationId(),
            preset: $preset,
            customFrom: $this->customFrom ? \Carbon\Carbon::parse($this->customFrom) : null,
            customTo: $this->customTo ? \Carbon\Carbon::parse($this->customTo) : null,
            employeeId: EmployeeContext::membership()->id,
            statuses: $this->callStatus ? [$this->callStatus] : [],
            direction: $this->directionFilter ?: null,
            minDurationSeconds: $this->durationMin ? $this->durationMin * 60 : null,
            maxDurationSeconds: $this->durationMax ? $this->durationMax * 60 : null,
            search: $this->search,
            sortBy: $this->sortBy,
            sortDir: $this->sortDir,
        );
    }
}
