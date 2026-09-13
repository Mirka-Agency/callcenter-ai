<?php

namespace App\Domain\Voip\Contracts;

use App\Domain\Voip\DTOs\CallLogData;
use App\Domain\Voip\Enums\VoipLogStatus;
use App\Domain\Voip\Enums\VoipOperation;

interface VoipLogRepositoryInterface
{
    public function logWebhook(
        int $connectionId,
        VoipLogStatus $status,
        ?array $payload = null,
        ?string $message = null,
        ?string $eventType = null,
        ?string $resolvedExtension = null,
        ?int $organizationUserId = null,
    ): void;

    public function logSync(
        int $connectionId,
        VoipOperation $operation,
        VoipLogStatus $status,
        ?array $payload = null,
        ?string $message = null,
        ?int $recordsProcessed = null,
    ): void;

    public function logOperation(
        int $connectionId,
        VoipOperation $operation,
        VoipLogStatus $status,
        ?array $request = null,
        ?array $response = null,
        ?string $message = null,
    ): void;
}
