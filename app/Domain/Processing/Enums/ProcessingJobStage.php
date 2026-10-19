<?php

namespace App\Domain\Processing\Enums;

enum ProcessingJobStage: string
{
    case Uploaded = 'uploaded';
    case Queued = 'queued';
    case Transcribing = 'transcribing';
    case SendingToAi = 'sending_to_ai';
    case WaitingForAi = 'waiting_for_ai';
    case ProcessingResult = 'processing_result';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => 'بارگذاری شد',
            self::Queued => 'در صف تحلیل',
            self::Transcribing => 'تبدیل گفتار به متن',
            self::SendingToAi => 'ارسال به هوش مصنوعی',
            self::WaitingForAi => 'در انتظار پاسخ هوش مصنوعی',
            self::ProcessingResult => 'آماده‌سازی نتیجه',
            self::Completed => 'تحلیل شد',
        };
    }

    public function progress(): int
    {
        return match ($this) {
            self::Uploaded => 15,
            self::Queued => 25,
            self::Transcribing => 35,
            self::SendingToAi => 45,
            self::WaitingForAi => 65,
            self::ProcessingResult => 85,
            self::Completed => 100,
        };
    }
}
