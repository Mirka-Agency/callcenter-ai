<?php

namespace App\Infrastructure\Voip\Repositories;

use App\Domain\Voip\Contracts\VoipLogRepositoryInterface;
use App\Domain\Voip\Enums\VoipLogStatus;
use App\Domain\Voip\Enums\VoipOperation;
use App\Models\VoipSyncLog;
use App\Models\VoipWebhookLog;

class EloquentVoipLogRepository implements VoipLogRepositoryInterface
{
    public function logWebhook(
        int $connectionId,
        VoipLogStatus $status,
        ?array $payload = null,
        ?string $message = null,
        ?string $eventType = null,
        ?string $resolvedExtension = null,
        ?int $organizationUserId = null,
    ): void {
        VoipWebhookLog::query()->create([
            'organization_voip_connection_id' => $connectionId,
            'event_type' => $eventType,
            'resolved_extension' => $resolvedExtension,
            'organization_user_id' => $organizationUserId,
            'status' => $status->value,
            'payload' => $payload,
            'message' => $message,
        ]);
    }

    public function logSync(
        int $connectionId,
        VoipOperation $operation,
        VoipLogStatus $status,
        ?array $payload = null,
        ?string $message = null,
        ?int $recordsProcessed = null,
    ): void {
        VoipSyncLog::query()->create([
            'organization_voip_connection_id' => $connectionId,
            'operation' => $operation->value,
            'status' => $status->value,
            'payload' => $payload,
            'message' => $message,
            'records_processed' => $recordsProcessed,
        ]);
    }

    public function logOperation(
        int $connectionId,
        VoipOperation $operation,
        VoipLogStatus $status,
        ?array $request = null,
        ?array $response = null,
        ?string $message = null,
    ): void {
        VoipSyncLog::query()->create([
            'organization_voip_connection_id' => $connectionId,
            'operation' => $operation->value,
            'status' => $status->value,
            'payload' => ['request' => $request, 'response' => $response],
            'message' => $message,
        ]);
    }
}
