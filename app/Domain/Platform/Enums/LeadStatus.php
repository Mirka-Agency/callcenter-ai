<?php

namespace App\Domain\Platform\Enums;

/** Sales pipeline stage of an inbound lead. */
enum LeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case DemoScheduled = 'demo_scheduled';
    case Trial = 'trial';
    case Converted = 'converted';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::New => 'جدید',
            self::Contacted => 'تماس گرفته شد',
            self::DemoScheduled => 'دمو تنظیم شد',
            self::Trial => 'دوره آزمایشی',
            self::Converted => 'تبدیل به مشتری',
            self::Lost => 'از دست رفته',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'gray',
            self::Contacted => 'info',
            self::DemoScheduled => 'warning',
            self::Trial => 'primary',
            self::Converted => 'success',
            self::Lost => 'danger',
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
