<?php

namespace App\Application\Crm\Services;

use App\Application\Crm\CrmManager;
use App\Application\Crm\Jobs\SyncCrmDataJob;
use App\Domain\Crm\ValueObjects\CrmOperationResult;
use App\Models\EmployeeIntegrationMeta;
use App\Models\OrganizationCrmConnection;
use App\Support\IntegrationCredentialMerger;

class CrmConnectionLifecycleService
{
    /** @param  array<string, mixed>  $data */
    public function create(int $organizationId, array $data): OrganizationCrmConnection
    {
        return OrganizationCrmConnection::query()->create([
            'organization_id' => $organizationId,
            'crm_provider_id' => $data['crm_provider_id'],
            'name' => $data['name'],
            'credentials' => $data['credentials'] ?? [],
            'settings' => $data['settings'] ?? [],
            'is_default' => (bool) ($data['is_default'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
    }

    /** @param  array<string, mixed>  $data */
    public function update(OrganizationCrmConnection $connection, array $data): OrganizationCrmConnection
    {
        $connection->update([
            'crm_provider_id' => $data['crm_provider_id'] ?? $connection->crm_provider_id,
            'name' => $data['name'] ?? $connection->name,
            'credentials' => IntegrationCredentialMerger::merge(
                $data['credentials'] ?? [],
                $connection->credentials,
            ),
            'settings' => IntegrationCredentialMerger::mergeSettings(
                $data['settings'] ?? [],
                $connection->settings,
            ),
            'is_default' => (bool) ($data['is_default'] ?? $connection->is_default),
            'is_active' => (bool) ($data['is_active'] ?? $connection->is_active),
        ]);

        return $connection->fresh(['provider']);
    }

    public function delete(OrganizationCrmConnection $connection): void
    {
        EmployeeIntegrationMeta::query()
            ->where('integratable_type', OrganizationCrmConnection::class)
            ->where('integratable_id', $connection->id)
            ->delete();

        $connection->delete();
    }

    public function test(OrganizationCrmConnection $connection): CrmOperationResult
    {
        return CrmManager::forOrganization($connection->organization_id)
            ->connection($connection->id)
            ->testConnection();
    }

    public function queueSync(OrganizationCrmConnection $connection, array $syncData = ['entity' => 'all']): void
    {
        SyncCrmDataJob::dispatch(
            organizationId: $connection->organization_id,
            connectionId: $connection->id,
            syncData: $syncData,
        );
    }
}
