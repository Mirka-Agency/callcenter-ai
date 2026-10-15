<?php

namespace App\Domain\Crm\Invoices;

enum InvoiceDocumentType: string
{
    case Proforma = 'proforma';
    case Invoice = 'invoice';

    public function label(): string
    {
        return match ($this) {
            self::Proforma => 'پیش‌فاکتور',
            self::Invoice => 'فاکتور',
        };
    }

    public function englishLabel(): string
    {
        return match ($this) {
            self::Proforma => 'PROFORMA',
            self::Invoice => 'INVOICE',
        };
    }

    public function numberPrefix(): string
    {
        return match ($this) {
            self::Proforma => 'PF',
            self::Invoice => 'INV',
        };
    }
}
