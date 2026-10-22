<?php

namespace App\Support;

use Illuminate\Support\Number;

class PersianNumber
{
    private const PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    private const ARABIC_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    private const LATIN_DIGITS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    public static function format(
        int|float|string|null $value,
        ?int $decimalPlaces = null,
        ?int $maxDecimalPlaces = null,
        string $locale = 'fa',
        ?bool $groupThousands = null,
    ): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return (string) $value;
        }

        $groupThousands ??= ! ($maxDecimalPlaces !== null && $decimalPlaces === null);

        if (! extension_loaded('intl')) {
            return Number::format((float) $value, $decimalPlaces, $maxDecimalPlaces, $locale);
        }

        $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);

        if (! $groupThousands) {
            $formatter->setAttribute(\NumberFormatter::GROUPING_USED, 0);
        }

        if ($maxDecimalPlaces !== null) {
            $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $maxDecimalPlaces);
        } elseif ($decimalPlaces !== null) {
            $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $decimalPlaces);
        }

        return $formatter->format((float) $value) ?: Number::format((float) $value, $decimalPlaces, $maxDecimalPlaces, $locale);
    }

    public static function currency(
        int|float|string|null $value,
        ?string $currency = null,
        string $locale = 'fa',
        ?int $decimalPlaces = null,
    ): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return (string) $value;
        }

        $currency ??= 'IRR';

        return Number::currency((float) $value, $currency, $locale, $decimalPlaces);
    }

    public static function parse(int|float|string|null $value): int|float|string|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        $normalized = self::toLatinDigits(trim((string) $value));
        $normalized = str_replace(['٬', ',', ' '], '', $normalized);
        $normalized = str_replace('٫', '.', $normalized);

        if ($normalized === '' || ! is_numeric($normalized)) {
            return $value;
        }

        return str_contains($normalized, '.')
            ? (float) $normalized
            : (int) $normalized;
    }

    public static function toLatinDigits(string $value): string
    {
        return str_replace(
            [...self::PERSIAN_DIGITS, ...self::ARABIC_DIGITS],
            [...self::LATIN_DIGITS, ...self::LATIN_DIGITS],
            $value,
        );
    }
}
