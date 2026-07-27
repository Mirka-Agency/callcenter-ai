<?php

namespace App\Application\Voip\Services;

use App\Application\Voip\Jobs\ProcessVoipIngestionJob;
use App\Domain\Voip\DTOs\NormalizedWebhookEvent;
use App\Domain\Voip\Enums\VoipEventSource;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Infrastructure\Voip\Support\WebhookPayloadNormalizer;
use App\Models\OrganizationVoipConnection;

class AsteriskAmiIngestionBridge
{
    public function __construct(
        private WebhookPayloadNormalizer $normalizer = new WebhookPayloadNormalizer,
    ) {}

    /** @param array<string, mixed> $payload */
    public function dispatch(OrganizationVoipConnection $connection, array $payload): void
    {
        $settings = $connection->settings ?? [];
        $normalized = $this->normalizer->normalize(
            payload: $payload,
            fieldMapping: $settings['webhook_field_mapping'] ?? [],
            provider: VoipProviderCode::Custom->value,
        )->withSource(VoipEventSource::Ami, 'custom');

        ProcessVoipIngestionJob::dispatch(
            connectionId: $connection->id,
            normalizedEvent: $normalized->toArray(),
        );
    }

    public function normalizeForTest(array $payload, OrganizationVoipConnection $connection): NormalizedWebhookEvent
    {
        $settings = $connection->settings ?? [];

        return $this->normalizer->normalize(
            payload: $payload,
            fieldMapping: $settings['webhook_field_mapping'] ?? [],
            provider: 'custom',
        )->withSource(VoipEventSource::Ami, 'custom');
    }
}
