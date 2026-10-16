<?php

namespace App\Domain\Crm\Deals;

enum DealSource: string
{
    case Manual = 'manual';
    case Analysis = 'analysis';
    case Import = 'import';
    case External = 'external';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'دستی',
            self::Analysis => 'تحلیل تماس',
            self::Import => 'ورود داده',
            self::External => 'اتصال خارجی',
        };
    }
}
