<?php

namespace App\Domain\Crm\Deals;

enum DealBarrierStatus: string
{
    case Suggested = 'suggested';
    case Confirmed = 'confirmed';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Suggested => 'پیشنهاد هوش مصنوعی',
            self::Confirmed => 'تأییدشده',
            self::Dismissed => 'ردشده',
        };
    }
}
