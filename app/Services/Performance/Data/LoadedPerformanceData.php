<?php

namespace App\Services\Performance\Data;

use App\DTOs\ReportFilter;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\OrganizationUser;
use Illuminate\Support\Collection;

class LoadedPerformanceData
{
    /** @var Collection<int, Collection<int, ConversationAnalysis>>|null */
    private ?Collection $groupedAnalyses = null;

    /** @var Collection<int, Collection<int, Call>>|null */
    private ?Collection $groupedCalls = null;

    /** @param  Collection<int, OrganizationUser>  $employees */
    /** @param  Collection<int, ConversationAnalysis>  $analyses */
    /** @param  Collection<int, Call>  $calls */
    public function __construct(
        public readonly ReportFilter $filter,
        public readonly Collection $employees,
        public readonly Collection $analyses,
        public readonly Collection $calls,
        public readonly ?self $previousPeriod = null,
    ) {}

    /** @return Collection<int, Collection<int, ConversationAnalysis>> */
    public function analysesByEmployee(): Collection
    {
        return $this->groupedAnalyses ??= $this->analyses->groupBy('organization_user_id');
    }

    /** @return Collection<int, Collection<int, Call>> */
    public function callsByEmployee(): Collection
    {
        return $this->groupedCalls ??= $this->calls->groupBy('organization_user_id');
    }

    public function analysesForEmployee(int $employeeId): Collection
    {
        return $this->analysesByEmployee()->get($employeeId, collect());
    }

    public function callsForEmployee(int $employeeId): Collection
    {
        return $this->callsByEmployee()->get($employeeId, collect());
    }
}
