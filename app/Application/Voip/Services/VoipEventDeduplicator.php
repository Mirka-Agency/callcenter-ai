<?php

namespace App\Application\Voip\Services;

use App\Domain\Voip\DTOs\NormalizedWebhookEvent;
use App\Domain\Voip\Enums\CallStatus;
use App\Domain\Voip\Enums\VoipWebhookEventType;
use App\Models\VoipCallLog;

class VoipEventDeduplicator
{
    public function isDuplicate(NormalizedWebhookEvent $event, ?VoipCallLog $existing): bool
    {
        if (! $existing || ! $event->callId) {
            return false;
        }

        return match ($event->type) {
            VoipWebhookEventType::CallStarted => $existing->started_at !== null
                || in_array($existing->status, [
                    CallStatus::Ringing,
                    CallStatus::Answered,
                    CallStatus::Completed,
                    CallStatus::Missed,
                    CallStatus::Busy,
                    CallStatus::Failed,
                    CallStatus::Cancelled,
                ], true),
            VoipWebhookEventType::CallAnswered => $existing->status === CallStatus::Answered
                || $existing->status === CallStatus::Completed,
            VoipWebhookEventType::CallEnded => $existing->ended_at !== null
                && $existing->status === CallStatus::Completed
                && ($event->recordingUrl === null || $existing->recording_url === $event->recordingUrl),
            VoipWebhookEventType::CallMissed => in_array($existing->status, [
                CallStatus::Missed,
                CallStatus::Busy,
                CallStatus::Failed,
                CallStatus::Cancelled,
            ], true),
            VoipWebhookEventType::RecordingCreated => $existing->recording_url !== null
                && ($event->recordingUrl === null || $existing->recording_url === $event->recordingUrl),
            default => false,
        };
    }
}
