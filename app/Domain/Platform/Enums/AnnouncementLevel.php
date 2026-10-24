<?php

namespace App\Domain\Platform\Enums;

enum AnnouncementLevel: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'اطلاع‌رسانی',
            self::Warning => 'هشدار',
            self::Maintenance => 'تعمیرات',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Info => 'info',
            self::Warning => 'warning',
            self::Maintenance => 'danger',
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
