<?php

namespace App\Domain\Voip\Enums;

enum VoipEventSource: string
{
    case Webhook = 'webhook';
    case Polling = 'polling';
    case Ami = 'ami';

    public function label(): string
    {
        return match ($this) {
            self::Webhook => 'وب‌هوک',
            self::Polling => 'نظرسنجی',
            self::Ami => 'AMI',
        };
    }
}
