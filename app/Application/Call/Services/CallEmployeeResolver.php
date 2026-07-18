<?php

namespace App\Application\Call\Services;

use App\Models\EmployeeIntegrationMeta;
use App\Models\OrganizationVoipConnection;
use App\Models\VoipCallLog;

class CallEmployeeResolver
{
    public function resolveFromCallLog(VoipCallLog $log): ?int
    {
        foreach ($this->extensionCandidates($log) as $extension) {
            $employeeId = $this->resolveByExtension(
                organizationId: (int) $log->organization_id,
                voipConnectionId: (int) $log->organization_voip_connection_id,
                extension: $extension,
            );

            if ($employeeId !== null) {
                return $employeeId;
            }
        }

        return null;
    }

    public function resolveByExtension(int $organizationId, int $voipConnectionId, string $extension): ?int
    {
        return EmployeeIntegrationMeta::query()
            ->where('integratable_type', OrganizationVoipConnection::class)
            ->where('integratable_id', $voipConnectionId)
            ->where('key', 'extension')
            ->where('value', $extension)
            ->whereHas('employee', fn ($q) => $q->where('organization_id', $organizationId))
            ->value('organization_user_id');
    }

    /** @return list<string> */
    private function extensionCandidates(VoipCallLog $log): array
    {
        $payload = is_array($log->raw_payload) ? $log->raw_payload : [];
        $candidates = [];

        foreach ([
            $payload['resolved_extension'] ?? null,
            $payload['exten'] ?? null,
            $log->direction?->value === 'inbound' ? $log->destination_number : $log->source_number,
            $log->destination_number,
            $log->source_number,
            $payload['did'] ?? null,
        ] as $value) {
            if (is_string($value) && $value !== '') {
                $candidates[] = $value;
            }
        }

        $mapping = $this->extensionMappingFor($log);

        foreach ($candidates as $candidate) {
            if (isset($mapping[$candidate]) && is_scalar($mapping[$candidate]) && (string) $mapping[$candidate] !== '') {
                $candidates[] = (string) $mapping[$candidate];
            }
        }

        return array_values(array_unique($candidates));
    }

    /** @return array<string, mixed> */
    private function extensionMappingFor(VoipCallLog $log): array
    {
        $connection = $log->relationLoaded('connection')
            ? $log->connection
            : OrganizationVoipConnection::query()->find($log->organization_voip_connection_id);

        $mapping = $connection?->settings['extension_mapping'] ?? [];

        return is_array($mapping) ? $mapping : [];
    }
}
