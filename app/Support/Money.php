<?php

namespace App\Support;

use App\Models\AdminSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Amounts are stored in rials. Admin finance screens display them in the unit chosen
 * in finance settings (rial or toman).
 */
class Money
{
    public const RIAL = 'rial';

    public const TOMAN = 'toman';

    private const CACHE_KEY = 'admin-settings:currency-display';

    public static function unit(): string
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(10), fn (): string => AdminSetting::valueFor(AdminSetting::CURRENCY_DISPLAY, self::TOMAN) === self::RIAL ? self::RIAL : self::TOMAN);
    }

    public static function forgetUnit(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function format(int|float|null $rials, bool $withUnit = true): string
    {
        if ($rials === null) {
            return '—';
        }

        $amount = self::unit() === self::TOMAN ? $rials / 10 : $rials;
        $formatted = PersianNumber::format(round($amount), 0) ?? '0';

        return $withUnit ? $formatted.' '.self::unitLabel() : $formatted;
    }

    public static function unitLabel(): string
    {
        return self::unit() === self::TOMAN ? 'تومان' : 'ریال';
    }

    /** Convert an amount typed in the display unit to rials. */
    public static function toRials(int|float|string|null $amount): int
    {
        $value = (float) PersianNumber::toLatinDigits(str_replace([',', '٬'], '', (string) ($amount ?? 0)));

        return (int) round(self::unit() === self::TOMAN ? $value * 10 : $value);
    }

    /** Convert stored rials to the display unit for form inputs. */
    public static function fromRials(int|float|null $rials): int|float
    {
        if ($rials === null) {
            return 0;
        }

        return self::unit() === self::TOMAN ? $rials / 10 : $rials;
    }
}
