<?php

namespace App\Services\CallIntake;

use App\Services\CallIntake\Contracts\CallIntakeFilter;
use App\Services\CallIntake\Filters\InternalAgentCallsFilter;
use App\Services\CallIntake\Filters\UnassignedAgentCallsFilter;

class CallIntakeFilterRegistry
{
    public function __construct(
        private InternalAgentCallsFilter $internalAgentCalls,
        private UnassignedAgentCallsFilter $unassignedAgentCalls,
    ) {}

    /**
     * Built-in filters, in the order shown on the organization profile.
     * Register another implementation here when an organization needs a custom rule.
     *
     * @return list<CallIntakeFilter>
     */
    public function all(): array
    {
        return [
            $this->internalAgentCalls,
            $this->unassignedAgentCalls,
        ];
    }

    public function find(string $key): ?CallIntakeFilter
    {
        foreach ($this->all() as $filter) {
            if ($filter->key() === $key) {
                return $filter;
            }
        }

        return null;
    }
}
