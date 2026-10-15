<?php

namespace App\Domain\Crm\Tasks;

enum TaskDueBasis: string
{
    case CustomerRequested = 'customer_requested';
    case LeadScore = 'lead_score';
    case Human = 'human';
    case Confirmed = 'confirmed';

    public function label(): string
    {
        return match ($this) {
            self::CustomerRequested => 'درخواست مشتری',
            self::LeadScore => 'پیشنهاد بر اساس امتیاز لید',
            self::Human => 'تعیین کارشناس',
            self::Confirmed => 'تأییدشده',
        };
    }
}
