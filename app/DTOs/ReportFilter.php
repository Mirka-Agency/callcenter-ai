<?php

namespace App\DTOs;

use App\Enums\ReportDatePreset;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

readonly class ReportFilter
{
    /** @param  list<int>  $employeeIds */
    public function __construct(
        public int $organizationId,
        public ReportDatePreset $preset,
        public Carbon $from,
        public Carbon $to,
        public array $employeeIds = [],
        public bool $compareMode = false,
        public ?string $drilldownDimension = null,
        public mixed $drilldownValue = null,
    ) {}

    public static function make(
        int $organizationId,
        ReportDatePreset $preset,
        ?Carbon $customFrom = null,
        ?Carbon $customTo = null,
        array $employeeIds = [],
        bool $compareMode = false,
    ): self {
        [$from, $to] = $preset->resolve($customFrom, $customTo);

        return new self(
            organizationId: $organizationId,
            preset: $preset,
            from: $from,
            to: $to,
            employeeIds: array_values(array_unique(array_map('intval', $employeeIds))),
            compareMode: $compareMode,
        );
    }

    public function withDrilldown(string $dimension, mixed $value): self
    {
        return new self(
            organizationId: $this->organizationId,
            preset: $this->preset,
            from: $this->from,
            to: $this->to,
            employeeIds: $this->employeeIds,
            compareMode: $this->compareMode,
            drilldownDimension: $dimension,
            drilldownValue: $value,
        );
    }

    public function previousPeriod(): self
    {
        $days = max(1, $this->from->diffInDays($this->to) + 1);

        return new self(
            organizationId: $this->organizationId,
            preset: $this->preset,
            from: $this->from->copy()->subDays($days)->startOfDay(),
            to: $this->from->copy()->subDay()->endOfDay(),
            employeeIds: $this->employeeIds,
            compareMode: $this->compareMode,
        );
    }

    public function granularity(): string
    {
        $days = $this->from->diffInDays($this->to) + 1;

        return $days > 60 ? 'week' : 'day';
    }

    public function cacheKey(): string
    {
        return implode(':', [
            $this->organizationId,
            $this->preset->value,
            $this->from->toDateString(),
            $this->to->toDateString(),
            implode('-', $this->employeeIds) ?: 'all',
            $this->compareMode ? '1' : '0',
        ]);
    }

    public function hasEmployeeFilter(): bool
    {
        return $this->employeeIds !== [];
    }

    public function applyToAnalysisQuery(Builder $query): Builder
    {
        $query->where('organization_id', $this->organizationId)
            ->whereBetween('analyzed_at', [$this->from, $this->to]);

        if ($this->employeeIds !== []) {
            $query->whereIn('organization_user_id', $this->employeeIds);
        }

        return $query;
    }

    public function applyToVoipQuery(Builder $query): Builder
    {
        $query->where('organization_id', $this->organizationId)
            ->occurredBetween($this->from, $this->to);

        return $query;
    }
}
