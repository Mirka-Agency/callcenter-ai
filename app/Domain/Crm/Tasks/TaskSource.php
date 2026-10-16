<?php

namespace App\Domain\Crm\Tasks;

enum TaskSource: string
{
    case Manual = 'manual';
    case Analysis = 'analysis';
    case System = 'system';
    case SalesFollowUp = 'sales_follow_up';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'دستی',
            self::Analysis => 'تحلیل تماس',
            self::System => 'سامانه',
            self::SalesFollowUp => 'پیگیری فروش',
        };
    }
}
