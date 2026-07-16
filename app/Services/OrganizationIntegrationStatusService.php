<?php

namespace App\Services;

use App\Domain\Crm\DTOs\CrmCredentials;
use App\Domain\Crm\Enums\CrmLogStatus;
use App\Domain\Crm\Enums\CrmOperation;
use App\Domain\Voip\DTOs\VoipCredentials;
use App\Domain\Voip\Enums\VoipLogStatus;
use App\Domain\Voip\Enums\VoipOperation;
use App\Enums\IntegrationSetupStatus;
use App\Models\Organization;
use App\Models\OrganizationCrmConnection;
use App\Models\OrganizationVoipConnection;

class OrganizationIntegrationStatusService
{
    public function forOrganization(Organization $organization): IntegrationReadinessData
    {
        return new IntegrationReadinessData(
            crmStatus: $this->crmStatus($organization),
            voipStatus: $this->voipStatus($organization),
        );
    }

    public function crmStatus(Organization $organization): IntegrationSetupStatus
    {
        $connection = $this->primaryCrmConnection($organization);

        if (! $connection) {
            return IntegrationSetupStatus::Incomplete;
        }

        return $this->isCrmConnectionComplete($connection)
            ? IntegrationSetupStatus::Complete
            : IntegrationSetupStatus::Incomplete;
    }

    public function voipStatus(Organization $organization): IntegrationSetupStatus
    {
        $connection = $this->primaryVoipConnection($organization);

        if (! $connection) {
            return IntegrationSetupStatus::Incomplete;
        }

        return $this->isVoipConnectionComplete($connection)
            ? IntegrationSetupStatus::Complete
            : IntegrationSetupStatus::Incomplete;
    }

    public function isCrmConnectionComplete(OrganizationCrmConnection $connection): bool
    {
        $connection->loadMissing('provider');

        if (! $connection->is_active) {
            return false;
        }

        $provider = $connection->provider;
        if (! $provider || ! $provider->is_active || blank($provider->code)) {
            return false;
        }

        $credentials = CrmCredentials::fromArray($connection->credentials ?? []);
        if (blank($credentials->apiUrl) || blank($credentials->authKey())) {
            return false;
        }

        return $connection->connectionLogs()
            ->where('operation', CrmOperation::TestConnection)
            ->where('status', CrmLogStatus::Success)
            ->exists();
    }

    public function isVoipConnectionComplete(OrganizationVoipConnection $connection): bool
    {
        $connection->loadMissing('provider');

        if (! $connection->is_active) {
            return false;
        }

        $provider = $connection->provider;
        if (! $provider || ! $provider->is_active || blank($provider->code) || blank($provider->adapter_class)) {
            return false;
        }

        $credentials = VoipCredentials::fromArray($connection->credentials ?? []);
        if (blank($credentials->apiUrl) || blank($credentials->authToken())) {
            return false;
        }

        if (! $this->voipIngestionConfigured($connection)) {
            return false;
        }

        return $connection->syncLogs()
            ->where('operation', VoipOperation::TestConnection)
            ->where('status', VoipLogStatus::Success)
            ->exists();
    }

    private function primaryCrmConnection(Organization $organization): ?OrganizationCrmConnection
    {
        return $organization->crmConnections()
            ->with('provider')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    private function primaryVoipConnection(Organization $organization): ?OrganizationVoipConnection
    {
        return $organization->voipConnections()
            ->with('provider')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    private function voipIngestionConfigured(OrganizationVoipConnection $connection): bool
    {
        $provider = $connection->provider;

        return $provider !== null && (bool) $provider->supports_webhook;
    }
}
