<?php

namespace App\Domain\Crm\Tasks;

enum TaskStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در انتظار',
            self::InProgress => 'در حال انجام',
            self::Completed => 'انجام‌شده',
            self::Cancelled => 'لغوشده',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::InProgress;
    }

    public function isTerminal(): bool
    {
        return $this === self::Completed || $this === self::Cancelled;
    }
}
