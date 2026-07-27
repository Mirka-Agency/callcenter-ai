<?php

namespace App\Livewire\Employer\Concerns;

use App\Models\OrganizationCrmConnection;
use App\Models\OrganizationUser;
use App\Models\OrganizationVoipConnection;
use App\Services\EmployeeIntegrationMetaService;
use App\Services\EmployerContext;
use App\Services\EmployerIntegrationGate;

trait ManagesEmployeeIntegrations
{
    /** @var list<array{connection: string, meta: array<string, string>}> */
    public array $integration_assignments = [];

    protected function bootEmployeeIntegrations(): void
    {
        if ($this->integration_assignments === []) {
            $this->integration_assignments[] = [
                'connection' => '',
                'meta' => [],
            ];
        }
    }

    protected function hydrateEmployeeIntegrationsFromMembership(?OrganizationUser $employee): void
    {
        if (! $employee) {
            $this->bootEmployeeIntegrations();

            return;
        }

        $assignments = EmployeeIntegrationMetaService::assignmentsFromEmployee($employee);

        if (! EmployerIntegrationGate::allowsFullManagement()) {
            $assignments = array_values(array_filter(
                $assignments,
                fn (array $assignment): bool => $this->isVoipAssignment($assignment),
            ));
        }

        $this->integration_assignments = $assignments;

        if ($this->integration_assignments === []) {
            $this->bootEmployeeIntegrations();
        }
    }

    protected function persistEmployeeIntegrations(OrganizationUser $employee): void
    {
        $organizationId = $employee->organization_id;

        if (EmployerIntegrationGate::allowsFullManagement()) {
            EmployeeIntegrationMetaService::syncForEmployee(
                employee: $employee,
                assignments: $this->integration_assignments,
                organizationId: $organizationId,
            );

            return;
        }

        // Without full management, employers may only edit VoIP extension mapping.
        // Preserve any existing CRM meta so sync does not wipe it.
        $existing = EmployeeIntegrationMetaService::assignmentsFromEmployee($employee);
        $crmAssignments = array_values(array_filter(
            $existing,
            fn (array $assignment): bool => $this->isCrmAssignment($assignment),
        ));
        $voipAssignments = array_values(array_filter(
            $this->integration_assignments,
            fn (array $assignment): bool => filled($assignment['connection'] ?? null) && $this->isVoipAssignment($assignment),
        ));

        EmployeeIntegrationMetaService::syncForEmployee(
            employee: $employee,
            assignments: array_merge($crmAssignments, $voipAssignments),
            organizationId: $organizationId,
        );
    }

    public function addIntegrationAssignment(): void
    {
        $this->integration_assignments[] = [
            'connection' => '',
            'meta' => [],
        ];
    }

    public function removeIntegrationAssignment(int $index): void
    {
        unset($this->integration_assignments[$index]);
        $this->integration_assignments = array_values($this->integration_assignments);
    }

    /** @return array<string, string> */
    public function integrationConnectionOptions(): array
    {
        $options = EmployeeIntegrationMetaService::connectionOptionsForOrganization(
            EmployerContext::organizationId(),
        );

        if (EmployerIntegrationGate::allowsFullManagement()) {
            return $options;
        }

        return array_filter(
            $options,
            fn (string $label, string $reference): bool => str_starts_with($reference, OrganizationVoipConnection::class.':'),
            ARRAY_FILTER_USE_BOTH,
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

    public function canManageFullIntegrations(): bool
    {
        return EmployerIntegrationGate::allowsFullManagement();
    }

    /** @param array{connection?: string} $assignment */
    private function isVoipAssignment(array $assignment): bool
    {
        return str_starts_with((string) ($assignment['connection'] ?? ''), OrganizationVoipConnection::class.':');
    }

    /** @param array{connection?: string} $assignment */
    private function isCrmAssignment(array $assignment): bool
    {
        return str_starts_with((string) ($assignment['connection'] ?? ''), OrganizationCrmConnection::class.':');
    }
}
