<?php

namespace App\Application\Crm\Invoices;

use App\Support\PersianNumber;
use Illuminate\Validation\ValidationException;

class InvoiceMoney
{
    public const CURRENCY = 'IRR';

    public static function parseRials(mixed $value, string $field = 'amount_rials'): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return self::assertRange($value, $field);
        }

        if (is_float($value)) {
            throw ValidationException::withMessages([
                $field => 'مبلغ باید عدد صحیح ریال باشد.',
            ]);
        }

        $parsed = PersianNumber::parse(trim((string) $value));

        if (is_float($parsed) || ! is_int($parsed)) {
            throw ValidationException::withMessages([
                $field => 'مبلغ باید عدد صحیح ریال باشد.',
            ]);
        }

        return self::assertRange($parsed, $field);
    }

    public static function formatRials(?int $rials): string
    {
        if ($rials === null) {
            return '—';
        }

        $formatted = number_format($rials, 0, '.', ',');
        $persian = str_replace(
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'],
            $formatted,
        );

        return $persian.' ریال';
    }

    public static function formatDigits(?int $rials): string
    {
        if ($rials === null) {
            return '۰';
        }

        $formatted = number_format($rials, 0, '.', ',');

        return str_replace(
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'],
            $formatted,
        );
    }

    /** Convert catalog تومان to ریال. */
    public static function tomansToRials(?int $tomans): int
    {
        if ($tomans === null) {
            return 0;
        }

        return self::assertRange($tomans * 10, 'unit_price_rials');
    }

    private static function assertRange(int $rials, string $field): int
    {
        if ($rials < 0 || $rials > 9_000_000_000_000_000) {
            throw ValidationException::withMessages([
                $field => 'مبلغ خارج از محدوده مجاز است.',
            ]);
        }

        return $rials;
    }
}
