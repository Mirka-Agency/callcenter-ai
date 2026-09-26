<?php

namespace App\Services\Performance\Data;

use App\DTOs\ReportFilter;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\OrganizationUser;
use App\Support\CompanyWorkCalendar;
use App\Support\OrganizationHolidays;
use Carbon\CarbonInterface;
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
        'conversation_date',
        'started_at',
        'created_at',
    ];

    private const ANALYSIS_CALL_COLUMNS = [
        'id',
        'conversation_date',
        'started_at',
        'created_at',
    ];

    public function load(ReportFilter $filter, bool $withPreviousPeriod = true): LoadedPerformanceData
    {
        $employees = $this->employees($filter);
        $employeeIds = $employees->pluck('id')->all();

        $holidayWeekdays = OrganizationHolidays::weekdays($filter->organizationId);
        $analyses = $this->withoutHolidays(
            $this->analyses($filter, $employeeIds),
            fn (ConversationAnalysis $analysis): ?CarbonInterface => $analysis->occurredAt(),
            $holidayWeekdays,
        );
        $calls = $this->withoutHolidays(
            $this->calls($filter, $employeeIds),
            fn (Call $call): ?CarbonInterface => $call->occurredAt(),
            $holidayWeekdays,
        );

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
            ->get(['id', 'user_id', 'first_name', 'last_name', 'gender', 'department', 'position', 'is_active']);
    }

    /** @param  list<int>  $employeeIds */
    private function analyses(ReportFilter $filter, array $employeeIds): Collection
    {
        if ($employeeIds === []) {
            return collect();
        }

        return $filter->applyToAnalysisQuery(ConversationAnalysis::query())
            ->whereIn('organization_user_id', $employeeIds)
            ->with(['call:'.implode(',', self::ANALYSIS_CALL_COLUMNS)])
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

    /**
     * Conversations on the company's holidays stay out of dashboard charts and score rollups.
     *
     * @template TValue of ConversationAnalysis|Call
     *
     * @param  Collection<int, TValue>  $items
     * @param  callable(TValue): ?CarbonInterface  $moment
     * @param  list<int>  $holidayWeekdays
     * @return Collection<int, TValue>
     */
    private function withoutHolidays(Collection $items, callable $moment, array $holidayWeekdays): Collection
    {
        return $items
            ->reject(function (ConversationAnalysis|Call $item) use ($moment, $holidayWeekdays): bool {
                $at = $moment($item);

                return $at instanceof CarbonInterface && CompanyWorkCalendar::isHolidayMoment($at, $holidayWeekdays);
            })
            ->values();
    }
}
