<?php

namespace App\Filament\Widgets\PlatformAnalytics\Concerns;

use App\Domain\Platform\Enums\AdminArea;
use App\Support\PersianNumber;

trait FormatsPlatformMetrics
{
    public static function canView(): bool
    {
        return auth()->user()?->hasAdminArea(AdminArea::Analytics) === true;
    }

    protected function percent(?float $ratio): string
    {
        return $ratio === null ? '—' : PersianNumber::format($ratio * 100, null, 1).'٪';
    }

    protected function number(int|float|null $value, ?int $maxDecimals = 0): string
    {
        return $value === null ? '—' : PersianNumber::format($value, null, $maxDecimals);
    }
}
