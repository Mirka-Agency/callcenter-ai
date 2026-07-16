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
use App\Infrastructure\Voip\Clients\SimotelApiClient;
use Carbon\Carbon;

class SimotelVoipAdapter extends AbstractVoipAdapter
{
    public const RECORDING_URL_PREFIX = 'simotel://';

    private const UNSUPPORTED = 'Operation is not supported by the Simotel VoIP adapter.';

    private ?SimotelApiClient $client = null;

    public function getProviderCode(): VoipProviderCode
    {
        return VoipProviderCode::Simotel;
    }

    public function testConnection(): VoipOperationResult
    {
        $response = $this->client()->post('reports/CDR', [
            'conditions' => [
                'from' => '',
                'to' => '',
                'cuid' => '',
            ],
            'date_range' => [
                'from' => now()->subMinute()->format('Y-m-d H:i'),
                'to' => now()->format('Y-m-d H:i'),
            ],
            'pagination' => [
                'start' => 0,
                'count' => 1,
                'sorting' => (object) [],
            ],
            'alike' => 'true',
        ]);

        if ($response->failed()) {
            return $this->parseHttpFailure(
                message: $response->json('message') ?? $response->json('error') ?? $response->body(),
                data: $response->json(),
            );
        }

        return VoipOperationResult::success(
            message: 'Simotel VoIP connection successful.',
            data: $response->json() ?? [],
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
        $file = $this->recordingFilenameFromIdentifier($callId);
        $response = $this->client()->downloadAudio($file);

        if ($response->failed()) {
            return $this->parseHttpFailure(
                message: $response->json('message') ?? $response->json('error') ?? $response->body(),
                data: $response->json(),
            );
        }

        return VoipOperationResult::success(
            externalId: $file,
            data: [
                'body' => $response->body(),
                'mime_type' => $response->header('Content-Type') ?? 'audio/mpeg',
            ],
            message: 'Call recording retrieved from Simotel.',
        );
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
        $eventName = strtolower((string) ($payload['event_name'] ?? $payload['event'] ?? ''));

        if ($eventName === 'cdr') {
            return $this->normalizeCdr($payload);
        }

        return new NormalizedWebhookEvent(
            type: VoipWebhookEventType::Unknown,
            rawPayload: $payload,
            provider: $this->getProviderCode()->value,
        );
    }

    private function normalizeCdr(array $payload): NormalizedWebhookEvent
    {
        $disposition = strtolower(str_replace(['_', '-'], ' ', (string) ($payload['disposition'] ?? '')));
        $record = $payload['record'] ?? null;
        $recordingUrl = is_string($record) && $record !== ''
            ? self::RECORDING_URL_PREFIX.$record
            : null;

        [$type, $status] = $this->mapDisposition($disposition, $recordingUrl !== null);

        $callId = (string) ($payload['unique_id'] ?? $payload['cuid'] ?? $payload['uniqueid'] ?? '');
        $direction = $this->mapDirection((string) ($payload['type'] ?? ''));
        $duration = isset($payload['duration'])
            ? (int) $payload['duration']
            : (isset($payload['billsec']) ? (int) $payload['billsec'] : null);

        $startedAt = $this->normalizeTimestamp(
            $payload['starttime'] ?? $payload['start_time'] ?? null,
        );
        $endedAt = $this->normalizeTimestamp(
            $payload['endtime'] ?? $payload['end_time'] ?? null,
        );

        $src = isset($payload['src']) ? (string) $payload['src'] : null;
        $dst = isset($payload['dst']) ? (string) $payload['dst'] : null;
        $extension = $direction === CallDirection::Inbound ? $dst : $src;

        return new NormalizedWebhookEvent(
            type: $type,
            callId: $callId !== '' ? $callId : null,
            direction: $direction,
            sourceNumber: $src,
            destinationNumber: $dst,
            status: $status,
            recordingUrl: $recordingUrl,
            extension: $extension,
            startedAt: $startedAt,
            endedAt: $endedAt,
            duration: $duration,
            rawPayload: $payload,
            provider: $this->getProviderCode()->value,
        );
    }

    /** @return array{0: VoipWebhookEventType, 1: CallStatus} */
    private function mapDisposition(string $disposition, bool $hasRecording): array
    {
        return match (true) {
            str_contains($disposition, 'no answer'),
            str_contains($disposition, 'noanswered') => [VoipWebhookEventType::CallMissed, CallStatus::Missed],
            str_contains($disposition, 'busy') => [VoipWebhookEventType::CallMissed, CallStatus::Busy],
            str_contains($disposition, 'answer') => [
                VoipWebhookEventType::CallEnded,
                CallStatus::Completed,
            ],
            $hasRecording => [VoipWebhookEventType::CallEnded, CallStatus::Completed],
            default => [VoipWebhookEventType::CallEnded, CallStatus::Completed],
        };
    }

    private function mapDirection(string $type): ?CallDirection
    {
        return match (strtolower(trim($type))) {
            'incoming', 'inbound' => CallDirection::Inbound,
            'outgoing', 'outbound' => CallDirection::Outbound,
            'local', 'feature', 'no defined', '' => CallDirection::Inbound,
            default => CallDirection::tryFrom(strtolower($type)),
        };
    }

    private function normalizeTimestamp(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toIso8601String();
        } catch (\Throwable) {
            return $value;
        }
    }

    private function recordingFilenameFromIdentifier(string $identifier): string
    {
        if (str_starts_with($identifier, self::RECORDING_URL_PREFIX)) {
            return substr($identifier, strlen(self::RECORDING_URL_PREFIX));
        }

        return $identifier;
    }

    private function client(): SimotelApiClient
    {
        return $this->client ??= new SimotelApiClient(
            credentials: $this->config->credentials,
            settings: $this->config->settings,
        );
    }
}
