<?php

namespace App\Infrastructure\Voip\Adapters;

use App\Domain\Voip\DTOs\ExtensionData;
use App\Domain\Voip\DTOs\MakeCallData;
use App\Domain\Voip\DTOs\NormalizedWebhookEvent;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Domain\Voip\ValueObjects\VoipOperationResult;
use App\Infrastructure\Voip\Support\WebhookPayloadNormalizer;

class CustomVoipAdapter extends AbstractVoipAdapter
{
    private const UNSUPPORTED = 'Operation is not supported by the custom VoIP adapter.';

    public function __construct(
        private WebhookPayloadNormalizer $normalizer = new WebhookPayloadNormalizer,
    ) {}

    public function getProviderCode(): VoipProviderCode
    {
        return VoipProviderCode::Custom;
    }

    public function testConnection(): VoipOperationResult
    {
        return VoipOperationResult::success(
            message: 'Custom VoIP webhook provider is ready. Send POST requests to the inbound webhook URL.',
        );
    }

    public function makeCall(MakeCallData $call): VoipOperationResult
    {
        return VoipOperationResult::failure(self::UNSUPPORTED);
    }

    public function hangupCall(string $callId): VoipOperationResult
    {
        return VoipOperationResult::failure(self::UNSUPPORTED);
    }

    public function getCallDetails(string $callId): VoipOperationResult
    {
        return VoipOperationResult::failure(self::UNSUPPORTED);
    }

    public function getCallRecording(string $callId): VoipOperationResult
    {
        return VoipOperationResult::failure(self::UNSUPPORTED);
    }

    public function getActiveCalls(): VoipOperationResult
    {
        return VoipOperationResult::failure(self::UNSUPPORTED);
    }

    public function createExtension(ExtensionData $extension): VoipOperationResult
    {
        return VoipOperationResult::failure(self::UNSUPPORTED);
    }

    public function updateExtension(string $extensionId, ExtensionData $extension): VoipOperationResult
    {
        return VoipOperationResult::failure(self::UNSUPPORTED);
    }

    public function getExtensions(): VoipOperationResult
    {
        return VoipOperationResult::failure(self::UNSUPPORTED);
    }

    public function normalizeWebhook(array $payload): NormalizedWebhookEvent
    {
        return $this->normalizer->normalize(
            payload: $payload,
            fieldMapping: $this->config->settings->webhookFieldMapping,
            provider: $this->getProviderCode()->value,
        );
    }

    /** @return array<string, mixed> */
    public static function sampleWebhookPayload(): array
    {
        return [
            'event' => 'call.ended',
            'call_id' => 'call-123',
            'direction' => 'inbound',
            'from' => '09121234567',
            'to' => '101',
            'status' => 'completed',
            'recording_url' => 'https://voip.example.com/recordings/call-123.mp3',
            'started_at' => '2026-07-16T10:00:00+03:30',
            'ended_at' => '2026-07-16T10:05:00+03:30',
            'duration' => 300,
            'extension' => '101',
        ];
    }
}
