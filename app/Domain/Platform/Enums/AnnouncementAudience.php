<?php

namespace App\Domain\Platform\Enums;

enum AnnouncementAudience: string
{
    case All = 'all';
    case Employers = 'employers';
    case Employees = 'employees';

    public function label(): string
    {
        return match ($this) {
            self::All => 'همه کاربران',
            self::Employers => 'کارفرمایان',
            self::Employees => 'کارشناسان',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
