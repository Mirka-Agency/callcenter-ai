<?php

namespace App\Services\Performance\Data;

use App\DTOs\ReportFilter;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\OrganizationUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PerformanceDataLoader
{
    private const ANALYSIS_COLUMNS = [
        'id',
        'organization_id',
        'organization_user_id',
        'call_id',
        'score',
        'is_evaluable',
        'sentiment',
        'summary',
        'lead_quality_json',
        'strengths_json',
        'weaknesses_json',
        'performance_dimensions_json',
        'analyzed_at',
    ];

    private const CALL_COLUMNS = [
        'id',
        'organization_id',
        'organization_user_id',
        'status',
        'duration_seconds',
        'caller_number',
        'customer_name',
        'started_at',
        'created_at',
    ];

    public function load(ReportFilter $filter, bool $withPreviousPeriod = true): LoadedPerformanceData
    {
        $employees = $this->employees($filter);
        $employeeIds = $employees->pluck('id')->all();

        $analyses = $this->analyses($filter, $employeeIds);
        $calls = $this->calls($filter, $employeeIds);

        $previous = $withPreviousPeriod
            ? $this->load($filter->previousPeriod(), withPreviousPeriod: false)
            : null;

        return new LoadedPerformanceData($filter, $employees, $analyses, $calls, $previous);
    }

    public function loadForEmployee(ReportFilter $filter, OrganizationUser $employee): LoadedPerformanceData
    {
        $scoped = new ReportFilter(
            organizationId: $filter->organizationId,
            preset: $filter->preset,
            from: $filter->from,
            to: $filter->to,
            employeeIds: [$employee->id],
            compareMode: $filter->compareMode,
        );

        return $this->load($scoped);
    }

    /** @return Collection<int, OrganizationUser> */
    private function employees(ReportFilter $filter): Collection
    {
        return OrganizationUser::query()
            ->where('organization_id', $filter->organizationId)
            ->where('is_active', true)
            ->when($filter->employeeIds !== [], fn (Builder $q) => $q->whereIn('id', $filter->employeeIds))
            ->with('user:id,avatar_path,name')
            ->orderBy('first_name')
            ->get(['id', 'user_id', 'first_name', 'last_name', 'department', 'position', 'is_active']);
    }

    /** @param  list<int>  $employeeIds */
    private function analyses(ReportFilter $filter, array $employeeIds): Collection
    {
        if ($employeeIds === []) {
            return collect();
        }

        return $filter->applyToAnalysisQuery(ConversationAnalysis::query())
            ->whereIn('organization_user_id', $employeeIds)
            ->orderBy('analyzed_at')
            ->get(self::ANALYSIS_COLUMNS);
    }

    /** @param  list<int>  $employeeIds */
    private function calls(ReportFilter $filter, array $employeeIds): Collection
    {
        if ($employeeIds === []) {
            return collect();
        }

        return Call::query()
            ->where('organization_id', $filter->organizationId)
            ->whereIn('organization_user_id', $employeeIds)
            ->where(function (Builder $q) use ($filter) {
                $q->whereBetween('started_at', [$filter->from, $filter->to])
                    ->orWhereBetween('created_at', [$filter->from, $filter->to]);
            })
            ->get(self::CALL_COLUMNS);
    }
}
