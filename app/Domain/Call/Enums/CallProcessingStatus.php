<?php

namespace App\Domain\Call\Enums;

enum CallProcessingStatus: string
{
    case Pending = 'pending';
    case Downloading = 'downloading';
    case Analyzing = 'analyzing';
    case Analyzed = 'analyzed';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در انتظار',
            self::Downloading => 'در حال دانلود',
            self::Analyzing => 'در حال تحلیل',
            self::Analyzed => 'تحلیل شد',
            self::Failed => 'ناموفق',
            self::Skipped => 'رد شده',
        };
    }
}
