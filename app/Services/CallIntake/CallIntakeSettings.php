<?php

namespace App\Services\CallIntake;

use App\Application\Intelligence\Services\CallAnalysisQueueService;
use App\Models\Organization;
use App\Services\Performance\EmployeePerformanceAnalytics;
use App\Services\Reports\OrganizationCallMetrics;

class CallIntakeSettings
{
    /** @var array<int, array<string, bool>> */
    private array $resolved = [];

    public function __construct(private CallIntakeFilterRegistry $registry) {}

    public function forget(int $organizationId): void
    {
        unset($this->resolved[$organizationId]);
    }

    /**
     * @return array<string, bool>
     */
    public function resolved(int $organizationId): array
    {
        if (isset($this->resolved[$organizationId])) {
            return $this->resolved[$organizationId];
        }

        $stored = Organization::query()->whereKey($organizationId)->value('call_intake_filters');
        $stored = is_array($stored) ? $stored : [];
        $resolved = [];

        foreach ($this->registry->all() as $filter) {
            $resolved[$filter->key()] = array_key_exists($filter->key(), $stored)
                ? (bool) $stored[$filter->key()]
                : $filter->defaultEnabled();
        }

        return $this->resolved[$organizationId] = $resolved;
    }

    public function enabled(int $organizationId, string $key): bool
    {
        return $this->resolved($organizationId)[$key] ?? false;
    }

    public function cacheToken(int $organizationId): string
    {
        return substr(md5(json_encode($this->resolved($organizationId)) ?: ''), 0, 12);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, bool>
     */
    public function normalize(array $input): array
    {
        $stored = [];

        foreach ($this->registry->all() as $filter) {
            $stored[$filter->key()] = (bool) ($input[$filter->key()] ?? false);
        }

        return $stored;
    }

    public function saved(Organization $organization, mixed $previous): void
    {
        $this->forget((int) $organization->id);

        $metrics = app(OrganizationCallMetrics::class);
        $metrics->forgetToday((int) $organization->id);

        if (is_array($previous)) {
            $metrics->forgetToday((int) $organization->id, $this->tokenFor($previous));
        }

        EmployeePerformanceAnalytics::forgetOrganizationCaches((int) $organization->id);
        app(CallAnalysisQueueService::class)->requeueAfterFilterChange((int) $organization->id);
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private function tokenFor(array $stored): string
    {
        $resolved = [];

        foreach ($this->registry->all() as $filter) {
            $resolved[$filter->key()] = array_key_exists($filter->key(), $stored)
                ? (bool) $stored[$filter->key()]
                : $filter->defaultEnabled();
        }

        return substr(md5(json_encode($resolved) ?: ''), 0, 12);
    }
}
