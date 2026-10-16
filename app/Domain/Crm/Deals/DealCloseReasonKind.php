<?php

namespace App\Domain\Crm\Deals;

enum DealCloseReasonKind: string
{
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Won => 'موفق',
            self::Lost => 'ناموفق',
        };
    }
}
