<?php

namespace App\Domain\Crm\Invoices;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Issued => 'صادر شده',
        };
    }
}
