<?php

namespace App\Domain\Crm\Enums;

enum CrmProviderCode: string
{
    case Didar = 'didar';
    case Dynamics = 'dynamics';

    public function label(): string
    {
        return match ($this) {
            self::Didar => 'Didar CRM',
            self::Dynamics => 'Microsoft Dynamics 365',
        };
    }

    public function defaultApiUrl(): ?string
    {
        return match ($this) {
            self::Didar => 'https://app.didar.me/api',
            self::Dynamics => null,
        };
    }

    public function isDynamics(): bool
    {
        return $this === self::Dynamics;
    }
}
