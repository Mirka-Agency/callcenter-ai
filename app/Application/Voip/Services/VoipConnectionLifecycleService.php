<?php

namespace App\Application\Voip\Services;

use App\Application\Voip\Jobs\SyncVoipExtensionsJob;
use App\Application\Voip\VoipManager;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Domain\Voip\ValueObjects\VoipOperationResult;
use App\Models\EmployeeIntegrationMeta;
use App\Models\OrganizationVoipConnection;
use App\Support\IntegrationCredentialMerger;

class VoipConnectionLifecycleService
{
    /** @param  array<string, mixed>  $data */
    public function create(int $organizationId, array $data): OrganizationVoipConnection
    {
        $token = OrganizationVoipConnection::normalizeWebhookTokenInput($data['webhook_token'] ?? null);

        $connection = OrganizationVoipConnection::query()->create([
            'organization_id' => $organizationId,
            'voip_provider_id' => $data['voip_provider_id'],
            'name' => $data['name'],
            'webhook_token' => $token,
            'credentials' => $data['credentials'] ?? [],
            'settings' => $data['settings'] ?? [],
            'is_default' => (bool) ($data['is_default'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'ingestion_mode' => $data['ingestion_mode'] ?? 'webhook',
        ]);

        $connection->loadMissing('provider');

        // Custom / Asterisk is webhook-only; mark ready so employers see setup guides immediately.
        if ($connection->provider?->code === VoipProviderCode::Custom->value) {
            $this->test($connection);
        }

        return $connection->fresh(['provider']);
    }

    /** @param  array<string, mixed>  $data */
    public function update(OrganizationVoipConnection $connection, array $data): OrganizationVoipConnection
    {
        $token = OrganizationVoipConnection::normalizeWebhookTokenInput($data['webhook_token'] ?? null);

        $connection->update([
            'voip_provider_id' => $data['voip_provider_id'] ?? $connection->voip_provider_id,
            'name' => $data['name'] ?? $connection->name,
            'webhook_token' => $token ?? $connection->webhook_token,
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
            'ingestion_mode' => $data['ingestion_mode'] ?? $connection->ingestion_mode,
        ]);

        return $connection->fresh(['provider']);
    }

    public function delete(OrganizationVoipConnection $connection): void
    {
        EmployeeIntegrationMeta::query()
            ->where('integratable_type', OrganizationVoipConnection::class)
            ->where('integratable_id', $connection->id)
            ->delete();

        $connection->delete();
    }

    public function test(OrganizationVoipConnection $connection): VoipOperationResult
    {
        return VoipManager::forOrganization($connection->organization_id)
            ->connection($connection->id)
            ->testConnection();
    }

    public function queueSyncExtensions(OrganizationVoipConnection $connection): void
    {
        SyncVoipExtensionsJob::dispatch($connection->organization_id, $connection->id);
    }

    public function regenerateWebhookToken(OrganizationVoipConnection $connection): string
    {
        return $connection->regenerateWebhookToken();
    }
}
