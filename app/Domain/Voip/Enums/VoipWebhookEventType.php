<?php

namespace App\Domain\Voip\Enums;

enum VoipWebhookEventType: string
{
    case CallStarted = 'call.started';
    case CallAnswered = 'call.answered';
    case CallEnded = 'call.ended';
    case CallMissed = 'call.missed';
    case RecordingCreated = 'recording.created';
    case ExtensionCreated = 'extension.created';
    case AgentStateChanged = 'agent.state_changed';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::CallStarted => 'شروع تماس',
            self::CallAnswered => 'پاسخ تماس',
            self::CallEnded => 'پایان تماس',
            self::CallMissed => 'تماس از دست‌رفته',
            self::RecordingCreated => 'ضبط ایجاد شد',
            self::ExtensionCreated => 'داخلی ایجاد شد',
            self::AgentStateChanged => 'تغییر وضعیت کارشناس',
            self::Unknown => 'نامشخص',
        };
    }
}
