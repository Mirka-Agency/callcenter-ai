<?php

namespace App\Domain\Voip\Enums;

enum VoipProviderCode: string
{
    case Novatel = 'novatel';
    case Simotel = 'simotel';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Novatel => 'Navatel',
            self::Simotel => 'Simotel',
            self::Custom => 'سفارشی',
        };
    }

    public function isWebhookOnly(): bool
    {
        return match ($this) {
            self::Custom => true,
            default => false,
        };
    }
}
