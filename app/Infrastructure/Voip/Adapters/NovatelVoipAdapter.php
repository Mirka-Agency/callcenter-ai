<?php

namespace App\Infrastructure\Voip\Adapters;

use App\Domain\Voip\DTOs\ExtensionData;
use App\Domain\Voip\DTOs\MakeCallData;
use App\Domain\Voip\DTOs\NormalizedWebhookEvent;
use App\Domain\Voip\Enums\CallDirection;
use App\Domain\Voip\Enums\CallStatus;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Domain\Voip\Enums\VoipWebhookEventType;
use App\Domain\Voip\ValueObjects\VoipOperationResult;
use App\Infrastructure\Voip\Clients\NovatelApiClient;

class NovatelVoipAdapter extends AbstractVoipAdapter
{
    private ?NovatelApiClient $client = null;

    public function getProviderCode(): VoipProviderCode
    {
        return VoipProviderCode::Novatel;
    }

    public function testConnection(): VoipOperationResult
    {
        $response = $this->client()->get('extensions', ['limit' => 1]);

        if ($response->failed()) {
            return $this->parseHttpFailure(
                message: $response->json('message') ?? $response->json('error') ?? $response->body(),
                data: $response->json(),
            );
        }

        return $this->parseResponse($response->json() ?? [], 'Novatel VoIP connection successful.');
    }

    public function makeCall(MakeCallData $call): VoipOperationResult
    {
        $response = $this->client()->post('calls/originate', [
            'from' => $call->from,
            'to' => $call->to,
            'caller_id' => $call->callerId,
            'extension' => $call->extension,
            'metadata' => $call->metadata,
        ]);

        if ($response->failed()) {
            return $this->parseHttpFailure(
                message: $response->json('message') ?? $response->json('error') ?? $response->body(),
                data: $response->json(),
            );
        }

        return $this->parseResponse($response->json() ?? [], 'Call initiated via Novatel.', 'call_id');
    }

    public function hangupCall(string $callId): VoipOperationResult
    {
        $response = $this->client()->post("calls/{$callId}/hangup");

        if ($response->failed()) {
            return $this->parseHttpFailure(
                message: $response->json('message') ?? $response->json('error') ?? $response->body(),
                data: $response->json(),
            );
        }

        return $this->parseResponse($response->json() ?? [], 'Call hung up via Novatel.', 'call_id');
    }

    public function getCallDetails(string $callId): VoipOperationResult
    {
        $response = $this->client()->get("calls/{$callId}");

        if ($response->failed()) {
            return $this->parseHttpFailure(
                message: $response->json('message') ?? $response->json('error') ?? $response->body(),
                data: $response->json(),
            );
        }

        return $this->parseResponse($response->json() ?? [], 'Call details retrieved from Novatel.', 'call_id');
    }

    public function getCallRecording(string $callId): VoipOperationResult
    {
        $response = $this->client()->get("calls/{$callId}/recording");

        if ($response->failed()) {
            return $this->parseHttpFailure(
                message: $response->json('message') ?? $response->json('error') ?? $response->body(),
                data: $response->json(),
            );
        }

        $body = $response->json() ?? [];

        return VoipOperationResult::success(
            externalId: $callId,
            data: $body,
            message: 'Call recording retrieved from Novatel.',
        );
    }

    public function getActiveCalls(): VoipOperationResult
    {
        $response = $this->client()->get('calls/active');

        if ($response->failed()) {
            return $this->parseHttpFailure(
                message: $response->json('message') ?? $response->json('error') ?? $response->body(),
                data: $response->json(),
            );
        }

        return $this->parseResponse($response->json() ?? [], 'Active calls retrieved from Novatel.');
    }

    public function createExtension(ExtensionData $extension): VoipOperationResult
    {
        $response = $this->client()->post('extensions', $extension->toArray());

        if ($response->failed()) {
            return $this->parseHttpFailure(
                message: $response->json('message') ?? $response->json('error') ?? $response->body(),
                data: $response->json(),
            );
        }

        return $this->parseResponse($response->json() ?? [], 'Extension created in Novatel.', 'extension_id');
    }

    public function updateExtension(string $extensionId, ExtensionData $extension): VoipOperationResult
    {
        $response = $this->client()->put("extensions/{$extensionId}", $extension->toArray());

        if ($response->failed()) {
            return $this->parseHttpFailure(
                message: $response->json('message') ?? $response->json('error') ?? $response->body(),
                data: $response->json(),
            );
        }

        return $this->parseResponse($response->json() ?? [], 'Extension updated in Novatel.', 'extension_id');
    }

    public function getExtensions(): VoipOperationResult
    {
        $response = $this->client()->get('extensions');

        if ($response->failed()) {
            return $this->parseHttpFailure(
                message: $response->json('message') ?? $response->json('error') ?? $response->body(),
                data: $response->json(),
            );
        }

        return $this->parseResponse($response->json() ?? [], 'Extensions retrieved from Novatel.');
    }

    public function normalizeWebhook(array $payload): NormalizedWebhookEvent
    {
        return $this->normalizeProviderPayload($payload);
    }

    private function normalizeProviderPayload(array $payload): NormalizedWebhookEvent
    {
        $eventType = $this->mapEventType($payload['event'] ?? $payload['event_type'] ?? $payload['type'] ?? $this->inferEventTypeFromStatus($payload));

        return new NormalizedWebhookEvent(
            type: $eventType,
            callId: (string) ($payload['call_id'] ?? $payload['callId'] ?? $payload['id'] ?? null),
            direction: CallDirection::tryFrom(strtolower((string) ($payload['direction'] ?? ''))),
            sourceNumber: $payload['from'] ?? $payload['source'] ?? $payload['caller'] ?? null,
            destinationNumber: $payload['to'] ?? $payload['destination'] ?? $payload['callee'] ?? null,
            status: CallStatus::tryFrom(strtolower((string) ($payload['status'] ?? ''))),
            recordingUrl: $payload['recording_url'] ?? $payload['recordingUrl'] ?? null,
            extension: $payload['extension'] ?? null,
            startedAt: $payload['started_at'] ?? $payload['start_time'] ?? null,
            endedAt: $payload['ended_at'] ?? $payload['end_time'] ?? null,
            duration: isset($payload['duration']) ? (int) $payload['duration'] : null,
            rawPayload: $payload,
            provider: $this->getProviderCode()->value,
        );
    }

    private function inferEventTypeFromStatus(array $payload): string
    {
        if (! empty($payload['recording_url']) || ! empty($payload['recordingUrl'])) {
            return 'recording.created';
        }

        return match (strtolower((string) ($payload['status'] ?? ''))) {
            'ringing', 'initiated' => 'call.started',
            'answered', 'in_progress' => 'call.answered',
            'completed', 'ended' => 'call.ended',
            'missed', 'no_answer' => 'call.missed',
            default => 'unknown',
        };
    }

    private function client(): NovatelApiClient
    {
        return $this->client ??= new NovatelApiClient(
            credentials: $this->config->credentials,
            settings: $this->config->settings,
        );
    }

    private function mapEventType(string $event): VoipWebhookEventType
    {
        return match (strtolower($event)) {
            'call.started', 'call_started', 'ringing', 'dial' => VoipWebhookEventType::CallStarted,
            'call.answered', 'call_answered', 'answered' => VoipWebhookEventType::CallAnswered,
            'call.ended', 'call_ended', 'hangup', 'completed' => VoipWebhookEventType::CallEnded,
            'call.missed', 'call_missed', 'missed', 'no_answer' => VoipWebhookEventType::CallMissed,
            'recording.created', 'recording_created', 'recording' => VoipWebhookEventType::RecordingCreated,
            'extension.created', 'extension_created' => VoipWebhookEventType::ExtensionCreated,
            default => VoipWebhookEventType::Unknown,
        };
    }
}
