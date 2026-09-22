<?php

namespace App\Domain\Voip\Enums;

enum CallStatus: string
{
    case Initiated = 'initiated';
    case Ringing = 'ringing';
    case Answered = 'answered';
    case Completed = 'completed';
    case Missed = 'missed';
    case Failed = 'failed';
    case Busy = 'busy';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Initiated => 'آغاز شده',
            self::Ringing => 'در حال زنگ',
            self::Answered => 'پاسخ داده شده',
            self::Completed => 'تکمیل شده',
            self::Missed => 'از دست رفته',
            self::Failed => 'ناموفق',
            self::Busy => 'مشغول',
            self::Cancelled => 'لغو شده',
        };
    }

    /**
     * Call outcomes that never produced a connected conversation.
     * Used for "تماس از دست رفته" stats (not only status=missed).
     *
     * @return list<self>
     */
    public static function lost(): array
    {
        return [
            self::Missed,
            self::Busy,
            self::Failed,
            self::Cancelled,
        ];
    }

    /** @return list<string> */
    public static function lostValues(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::lost(),
        );
    }
}
