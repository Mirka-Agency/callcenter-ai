<?php

namespace App\Domain\Crm\Deals;

enum DealSuggestionType: string
{
    case CreateDeal = 'create_deal';
    case ReviewLink = 'review_link';
    case LinkCall = 'link_call';

    public function label(): string
    {
        return match ($this) {
            self::CreateDeal => 'پیشنهاد ساخت معامله',
            self::ReviewLink => 'انتخاب معامله مرتبط',
            self::LinkCall => 'اتصال تماس',
        };
    }
}
