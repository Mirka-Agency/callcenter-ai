<?php

namespace App\Livewire\Employer\Concerns;

use App\Models\OrganizationUser;
use App\Services\EmployeeIntegrationMetaService;
use App\Services\EmployerContext;
use App\Services\EmployerIntegrationGate;

trait ManagesEmployeeIntegrations
{
    /** @var list<array{connection: string, meta: array<string, string>}> */
    public array $integration_assignments = [];

    protected function bootEmployeeIntegrations(): void
    {
        if (! EmployerIntegrationGate::allowsFullManagement()) {
            return;
        }

        if ($this->integration_assignments === []) {
            $this->integration_assignments[] = [
                'connection' => '',
                'meta' => [],
            ];
        }
    }

    protected function hydrateEmployeeIntegrationsFromMembership(?OrganizationUser $employee): void
    {
        if (! EmployerIntegrationGate::allowsFullManagement() || ! $employee) {
            return;
        }

        $this->integration_assignments = EmployeeIntegrationMetaService::assignmentsFromEmployee($employee);

        if ($this->integration_assignments === []) {
            $this->bootEmployeeIntegrations();
        }
    }

    protected function persistEmployeeIntegrations(OrganizationUser $employee): void
    {
        if (! EmployerIntegrationGate::allowsFullManagement()) {
            return;
        }

        EmployeeIntegrationMetaService::syncForEmployee(
            employee: $employee,
            assignments: $this->integration_assignments,
            organizationId: $employee->organization_id,
        );
    }

    public function addIntegrationAssignment(): void
    {
        EmployerIntegrationGate::authorizeFullManagement();

        $this->integration_assignments[] = [
            'connection' => '',
            'meta' => [],
        ];
    }

    public function removeIntegrationAssignment(int $index): void
    {
        EmployerIntegrationGate::authorizeFullManagement();

        unset($this->integration_assignments[$index]);
        $this->integration_assignments = array_values($this->integration_assignments);
    }

    /** @return array<string, string> */
    public function integrationConnectionOptions(): array
    {
        return EmployeeIntegrationMetaService::connectionOptionsForOrganization(
            EmployerContext::organizationId(),
        );
    }

    /** @return list<array{key: string, name: string, required: bool, type: string, placeholder: ?string}> */
    public function metaFieldsForAssignment(int $index): array
    {
        $connection = $this->integration_assignments[$index]['connection'] ?? null;

        return EmployeeIntegrationMetaService::metaFieldDefinitionsForReference(
            is_string($connection) ? $connection : null,
            EmployerContext::organizationId(),
        );
    }
}
