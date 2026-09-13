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

    /**
     * Resolve against a preloaded extension map to avoid per-call DB lookups.
     *
     * @param  array<string, int>  $extensionEmployeeMap  keys: "{connectionId}|{extension}"
     */
    public function resolveFromCallLogUsingMap(VoipCallLog $log, array $extensionEmployeeMap): ?int
    {
        $connectionId = (int) $log->organization_voip_connection_id;

        foreach ($this->extensionCandidates($log) as $extension) {
            $employeeId = $extensionEmployeeMap[$connectionId.'|'.$extension] ?? null;

            if ($employeeId !== null) {
                return $employeeId;
            }
        }

        return null;
    }

    /**
     * Prefetch all VoIP extension → employee mappings for an organization.
     *
     * @return array<string, int> keys: "{connectionId}|{extension}"
     */
    public function extensionEmployeeMapForOrganization(int $organizationId): array
    {
        $map = [];

        $metas = EmployeeIntegrationMeta::query()
            ->where('integratable_type', OrganizationVoipConnection::class)
            ->where('key', 'extension')
            ->whereHas('employee', fn ($q) => $q->where('organization_id', $organizationId))
            ->get(['organization_user_id', 'integratable_id', 'value']);

        foreach ($metas as $meta) {
            $extension = trim((string) $meta->value);

            if ($extension === '') {
                continue;
            }

            $map[((int) $meta->integratable_id).'|'.$extension] = (int) $meta->organization_user_id;
        }

        return $map;
    }

    public function resolveByExtension(int $organizationId, int $voipConnectionId, string $extension): ?int
    {
        $extension = trim($extension);

        if ($extension === '') {
            return null;
        }

        return EmployeeIntegrationMeta::query()
            ->where('integratable_type', OrganizationVoipConnection::class)
            ->where('integratable_id', $voipConnectionId)
            ->where('key', 'extension')
            ->where('value', $extension)
            ->whereHas('employee', fn ($q) => $q->where('organization_id', $organizationId))
            ->value('organization_user_id');
    }

    /** @return list<string> */
    public function extensionCandidates(VoipCallLog $log): array
    {
        $payload = is_array($log->raw_payload) ? $log->raw_payload : [];
        $candidates = [];

        foreach ([
            $payload['resolved_extension'] ?? null,
            $payload['extension'] ?? null,
            $payload['agent_extension'] ?? null,
            $payload['internal_number'] ?? null,
            $payload['exten'] ?? null,
            $log->direction?->value === 'inbound' ? $log->destination_number : $log->source_number,
            $log->destination_number,
            $log->source_number,
            $payload['did'] ?? null,
        ] as $value) {
            $normalized = $this->normalizeCandidate($value);

            if ($normalized !== null) {
                $candidates[] = $normalized;
            }
        }

        $mapping = $this->extensionMappingFor($log);

        foreach ($candidates as $candidate) {
            if (! isset($mapping[$candidate])) {
                continue;
            }

            $mapped = $this->normalizeCandidate($mapping[$candidate]);

            if ($mapped !== null) {
                $candidates[] = $mapped;
            }
        }

        return array_values(array_unique($candidates));
    }

    private function normalizeCandidate(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
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
