<?php

namespace App\Application\Voip\Services;

use App\Domain\Voip\DTOs\CallLogData;
use App\Domain\Voip\DTOs\NormalizedWebhookEvent;
use App\Domain\Voip\Enums\CallDirection;
use App\Domain\Voip\Enums\CallStatus;
use App\Domain\Voip\Enums\VoipWebhookEventType;
use App\Domain\Voip\Events\CallAnswered;
use App\Domain\Voip\Events\CallEnded;
use App\Domain\Voip\Events\CallMissed;
use App\Domain\Voip\Events\CallStarted;
use App\Domain\Voip\Events\ExtensionCreated;
use App\Domain\Voip\Events\RecordingCreated;
use Illuminate\Support\Carbon;

class VoipWebhookDispatcher
{
    public function dispatch(int $organizationId, int $connectionId, NormalizedWebhookEvent $event): void
    {
        match ($event->type) {
            VoipWebhookEventType::CallStarted => event(new CallStarted($organizationId, $connectionId, $event)),
            VoipWebhookEventType::CallAnswered => event(new CallAnswered($organizationId, $connectionId, $event)),
            VoipWebhookEventType::CallEnded => event(new CallEnded($organizationId, $connectionId, $event)),
            VoipWebhookEventType::CallMissed => event(new CallMissed($organizationId, $connectionId, $event)),
            VoipWebhookEventType::RecordingCreated => event(new RecordingCreated($organizationId, $connectionId, $event)),
            VoipWebhookEventType::ExtensionCreated => event(new ExtensionCreated($organizationId, $connectionId, $event)),
            default => null,
        };
    }

    public function toCallLogData(
        int $organizationId,
        int $connectionId,
        string $providerCode,
        NormalizedWebhookEvent $event,
    ): ?CallLogData {
        if (! $event->callId) {
            return null;
        }

        if ($event->type === VoipWebhookEventType::AgentStateChanged) {
            return null;
        }

        $rawPayload = $event->rawPayload;
        if ($event->extension !== null && $event->extension !== '') {
            $rawPayload['resolved_extension'] = $event->extension;
        }

        return new CallLogData(
            organizationId: $organizationId,
            connectionId: $connectionId,
            providerCode: $providerCode,
            externalCallId: $event->callId,
            direction: $event->direction ?? CallDirection::Outbound,
            sourceNumber: $event->sourceNumber ?? '',
            destinationNumber: $event->destinationNumber ?? '',
            status: $event->status ?? $this->statusFromEvent($event->type),
            startedAt: $this->startedAtFor($event),
            endedAt: $this->endedAtFor($event),
            duration: $event->duration,
            recordingUrl: $event->recordingUrl,
            rawPayload: $rawPayload,
        );
    }

    private function statusFromEvent(VoipWebhookEventType $type): CallStatus
    {
        return match ($type) {
            VoipWebhookEventType::CallStarted => CallStatus::Ringing,
            VoipWebhookEventType::CallAnswered => CallStatus::Answered,
            VoipWebhookEventType::CallEnded => CallStatus::Completed,
            VoipWebhookEventType::CallMissed => CallStatus::Missed,
            default => CallStatus::Initiated,
        };
    }

    /**
     * Issabel/Asterisk CDR webhooks often omit started_at and only send duration
     * on hangup. Infer the window so dashboard "today" counts match ingested calls.
     */
    private function startedAtFor(NormalizedWebhookEvent $event): string
    {
        if (filled($event->startedAt)) {
            return $this->toAppTime($event->startedAt);
        }

        if ($event->type === VoipWebhookEventType::CallStarted) {
            return now()->toDateTimeString();
        }

        if ($event->duration !== null && $event->duration > 0) {
            return now()->subSeconds($event->duration)->toDateTimeString();
        }

        return now()->toDateTimeString();
    }

    private function endedAtFor(NormalizedWebhookEvent $event): ?string
    {
        if (filled($event->endedAt)) {
            return $this->toAppTime($event->endedAt);
        }

        return match ($event->type) {
            VoipWebhookEventType::CallEnded,
            VoipWebhookEventType::CallMissed,
            VoipWebhookEventType::RecordingCreated => now()->toDateTimeString(),
            default => null,
        };
    }

    /**
     * Timestamp columns have no zone, so an explicit offset (+03:30, Z) must be
     * converted here or the wall-clock time is stored as if it were app time.
     */
    private function toAppTime(string $value): string
    {
        if (! preg_match('/(Z|[+-]\d{2}:?\d{2})$/i', trim($value))) {
            return $value;
        }

        try {
            return Carbon::parse($value)->setTimezone(config('app.timezone'))->toDateTimeString();
        } catch (\Throwable) {
            return $value;
        }
    }
}
