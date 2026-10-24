<?php

namespace App\Domain\Platform\Enums;

/** Sections of the admin panel a non-super admin can be granted. */
enum AdminArea: string
{
    case Analytics = 'analytics';
    case Support = 'support';
    case Sales = 'sales';
    case Finance = 'finance';
    case Content = 'content';

    public function label(): string
    {
        return match ($this) {
            self::Analytics => 'تحلیل کسب‌وکار',
            self::Support => 'پشتیبانی',
            self::Sales => 'فروش',
            self::Finance => 'مالی',
            self::Content => 'محتوا',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Analytics => 'primary',
            self::Support => 'info',
            self::Sales => 'warning',
            self::Finance => 'success',
            self::Content => 'gray',
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
