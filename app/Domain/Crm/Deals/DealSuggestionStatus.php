<?php

namespace App\Domain\Crm\Deals;

enum DealSuggestionStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Dismissed = 'dismissed';
    case AutoLinked = 'auto_linked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'نیازمند بررسی',
            self::Accepted => 'تأییدشده',
            self::Dismissed => 'ردشده',
            self::AutoLinked => 'اتصال خودکار',
        };
    }
}
