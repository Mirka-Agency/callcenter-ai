<?php

namespace App\Domain\Voip\Enums;

enum CallDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';

    public function label(): string
    {
        return match ($this) {
            self::Inbound => 'ورودی',
            self::Outbound => 'خروجی',
        };
    }

    public function analysisPromptLabel(): string
    {
        return match ($this) {
            self::Inbound => 'Inbound Call (تماس ورودی)',
            self::Outbound => 'Outbound Call (تماس خروجی)',
        };
    }
}
