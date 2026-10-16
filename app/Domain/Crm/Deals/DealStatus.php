<?php

namespace App\Domain\Crm\Deals;

enum DealStatus: string
{
    case Open = 'open';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'باز',
            self::Won => 'موفق',
            self::Lost => 'ناموفق',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Open;
    }

    public function isClosed(): bool
    {
        return $this !== self::Open;
    }
}
