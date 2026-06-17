<?php

namespace App\Domain\Processing\Enums;

enum ProcessingJobStatus: string
{
    case Queued = 'queued';
    case Uploading = 'uploading';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'در صف',
            self::Uploading => 'در حال بارگذاری',
            self::Processing => 'در حال تحلیل',
            self::Completed => 'تحلیل شد',
            self::Failed => 'ناموفق',
            self::Cancelled => 'لغو شد',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Cancelled], true);
    }

    public function isRecoverable(): bool
    {
        return in_array($this, [self::Failed, self::Cancelled], true);
    }
}
