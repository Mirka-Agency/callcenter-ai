<?php

namespace App\Domain\Crm\Tasks;

enum TaskPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'کم',
            self::Normal => 'عادی',
            self::High => 'بالا',
            self::Urgent => 'فوری',
        };
    }
}
