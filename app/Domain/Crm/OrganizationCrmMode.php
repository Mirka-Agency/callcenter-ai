<?php

namespace App\Domain\Crm;

enum OrganizationCrmMode: string
{
    case Internal = 'internal';
    case External = 'external';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'CRM آوایار',
            self::External => 'CRM خارجی',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $mode) => [$mode->value => $mode->label()])
            ->all();
    }
}
