<?php

namespace App\Domain\Crm\Tasks;

enum TaskSuggestionStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در انتظار بررسی',
            self::Accepted => 'تأییدشده',
            self::Dismissed => 'ردشده',
        };
    }
}
