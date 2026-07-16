<?php

namespace App\Infrastructure\Voip\Support;

use App\Domain\Voip\DTOs\NormalizedWebhookEvent;
use App\Domain\Voip\Enums\CallDirection;
use App\Domain\Voip\Enums\CallStatus;
use App\Domain\Voip\Enums\VoipWebhookEventType;

class WebhookPayloadNormalizer
{
    /** @param array<string, string> $fieldMapping */
    public function normalize(array $payload, array $fieldMapping = [], ?string $provider = null): NormalizedWebhookEvent
    {
        $eventRaw = $this->value($payload, 'event', $fieldMapping)
            ?? $this->inferEventTypeFromPayload($payload, $fieldMapping);

        $recordingUrl = $this->stringValue($payload, 'recording_url', $fieldMapping);
        $statusRaw = $this->stringValue($payload, 'status', $fieldMapping);
        $directionRaw = $this->stringValue($payload, 'direction', $fieldMapping);

        return new NormalizedWebhookEvent(
            type: $this->mapEventType((string) $eventRaw),
            callId: $this->stringValue($payload, 'call_id', $fieldMapping),
            direction: $this->mapDirection($directionRaw),
            sourceNumber: $this->stringValue($payload, 'from', $fieldMapping),
            destinationNumber: $this->stringValue($payload, 'to', $fieldMapping),
            status: $this->mapStatus($statusRaw, $eventRaw),
            recordingUrl: $recordingUrl,
            extension: $this->stringValue($payload, 'extension', $fieldMapping),
            startedAt: $this->stringValue($payload, 'started_at', $fieldMapping),
            endedAt: $this->stringValue($payload, 'ended_at', $fieldMapping),
            duration: $this->intValue($payload, 'duration', $fieldMapping),
            rawPayload: $payload,
            provider: $provider,
        );
    }

    /** @param array<string, string> $fieldMapping */
    private function value(array $payload, string $field, array $fieldMapping): mixed
    {
        if (isset($fieldMapping[$field]) && is_string($fieldMapping[$field]) && $fieldMapping[$field] !== '') {
            $mapped = data_get($payload, $fieldMapping[$field]);

            if ($mapped !== null && $mapped !== '') {
                return $mapped;
            }
        }

        return match ($field) {
            'event' => $payload['event']
                ?? $payload['event_type']
                ?? $payload['type']
                ?? $payload['event_name']
                ?? null,
            'call_id' => $payload['call_id']
                ?? $payload['callId']
                ?? $payload['id']
                ?? $payload['unique_id']
                ?? $payload['uniqueid']
                ?? $payload['cuid']
                ?? null,
            'from' => $payload['from']
                ?? $payload['source']
                ?? $payload['caller']
                ?? $payload['src']
                ?? $payload['caller_number']
                ?? $payload['phone']
                ?? null,
            'to' => $payload['to']
                ?? $payload['destination']
                ?? $payload['callee']
                ?? $payload['dst']
                ?? $payload['receiver_number']
                ?? null,
            'direction' => $payload['direction']
                ?? $payload['call_direction']
                ?? $payload['call_type']
                ?? null,
            'status' => $payload['status']
                ?? $payload['disposition']
                ?? $payload['call_status']
                ?? null,
            'recording_url' => $payload['recording_url']
                ?? $payload['recordingUrl']
                ?? $payload['record']
                ?? $payload['audio_url']
                ?? $payload['audioUrl']
                ?? $payload['recording_link']
                ?? $payload['audio_link']
                ?? null,
            'extension' => $payload['extension']
                ?? $payload['agent_extension']
                ?? $payload['internal_number']
                ?? null,
            'started_at' => $payload['started_at']
                ?? $payload['startedAt']
                ?? $payload['start_time']
                ?? $payload['starttime']
                ?? $payload['startTime']
                ?? null,
            'ended_at' => $payload['ended_at']
                ?? $payload['endedAt']
                ?? $payload['end_time']
                ?? $payload['endtime']
                ?? $payload['endTime']
                ?? null,
            'duration' => $payload['duration']
                ?? $payload['billsec']
                ?? $payload['talk_time']
                ?? null,
            default => null,
        };
    }

    /** @param array<string, string> $fieldMapping */
    private function stringValue(array $payload, string $field, array $fieldMapping): ?string
    {
        $value = $this->value($payload, $field, $fieldMapping);

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /** @param array<string, string> $fieldMapping */
    private function intValue(array $payload, string $field, array $fieldMapping): ?int
    {
        $value = $this->value($payload, $field, $fieldMapping);

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /** @param array<string, string> $fieldMapping */
    private function inferEventTypeFromPayload(array $payload, array $fieldMapping): string
    {
        if ($this->stringValue($payload, 'recording_url', $fieldMapping)) {
            return 'recording.created';
        }

        $status = strtolower((string) ($this->stringValue($payload, 'status', $fieldMapping) ?? ''));

        return match (true) {
            in_array($status, ['ringing', 'initiated', 'dial'], true) => 'call.started',
            in_array($status, ['answered', 'in_progress'], true) => 'call.answered',
            in_array($status, ['completed', 'ended', 'answered'], true) => 'call.ended',
            in_array($status, ['missed', 'no_answer', 'no answer', 'busy'], true) => 'call.missed',
            default => 'call.ended',
        };
    }

    private function mapEventType(string $event): VoipWebhookEventType
    {
        return match (strtolower($event)) {
            'call.started', 'call_started', 'ringing', 'dial', 'newstate' => VoipWebhookEventType::CallStarted,
            'call.answered', 'call_answered', 'answered' => VoipWebhookEventType::CallAnswered,
            'call.ended', 'call_ended', 'hangup', 'completed', 'cdr' => VoipWebhookEventType::CallEnded,
            'call.missed', 'call_missed', 'missed', 'no_answer', 'no answer' => VoipWebhookEventType::CallMissed,
            'recording.created', 'recording_created', 'recording' => VoipWebhookEventType::RecordingCreated,
            'extension.created', 'extension_created' => VoipWebhookEventType::ExtensionCreated,
            default => VoipWebhookEventType::Unknown,
        };
    }

    private function mapDirection(?string $direction): ?CallDirection
    {
        if ($direction === null || $direction === '') {
            return null;
        }

        $normalized = strtolower(str_replace(['_', '-'], ' ', $direction));

        return match (true) {
            in_array($normalized, ['inbound', 'incoming', 'in'], true) => CallDirection::Inbound,
            in_array($normalized, ['outbound', 'outgoing', 'out'], true) => CallDirection::Outbound,
            default => CallDirection::tryFrom(strtolower($direction)),
        };
    }

    private function mapStatus(?string $status, mixed $eventRaw): ?CallStatus
    {
        if ($status !== null && $status !== '') {
            $normalized = strtolower(str_replace(['_', '-'], ' ', $status));

            return match (true) {
                str_contains($normalized, 'ring') => CallStatus::Ringing,
                str_contains($normalized, 'answer') && ! str_contains($normalized, 'no') => CallStatus::Answered,
                str_contains($normalized, 'complete') || str_contains($normalized, 'end') => CallStatus::Completed,
                str_contains($normalized, 'miss') || str_contains($normalized, 'no answer') => CallStatus::Missed,
                str_contains($normalized, 'busy') => CallStatus::Busy,
                str_contains($normalized, 'fail') => CallStatus::Failed,
                str_contains($normalized, 'cancel') => CallStatus::Cancelled,
                default => CallStatus::tryFrom(strtolower($status)),
            };
        }

        return match ($this->mapEventType((string) $eventRaw)) {
            VoipWebhookEventType::CallStarted => CallStatus::Ringing,
            VoipWebhookEventType::CallAnswered => CallStatus::Answered,
            VoipWebhookEventType::CallEnded => CallStatus::Completed,
            VoipWebhookEventType::CallMissed => CallStatus::Missed,
            default => null,
        };
    }
}
