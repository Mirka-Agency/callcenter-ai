<?php

namespace App\Domain\Intelligence\Enums;

enum ReanalyzeScope: string
{
    case Under20 = 'under_20';
    case Under50 = 'under_50';
    case All = 'all';

    public function maxScore(): ?int
    {
        return match ($this) {
            self::Under20 => 20,
            self::Under50 => 50,
            self::All => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Under20 => 'امتیاز ۲۰ و کمتر',
            self::Under50 => 'امتیاز ۵۰ و کمتر',
            self::All => 'همه تحلیل‌ها',
        };
    }
}
