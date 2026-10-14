<?php

namespace App\Application\Crm\Deals;

use App\Support\PersianNumber;
use Illuminate\Validation\ValidationException;

class DealMoney
{
    public const CURRENCY = 'IRR';

    public static function parseRials(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return self::assertRange($value);
        }

        if (is_float($value)) {
            throw ValidationException::withMessages([
                'amount_rials' => 'مبلغ باید عدد صحیح ریال باشد.',
            ]);
        }

        $parsed = PersianNumber::parse(trim((string) $value));

        if (is_float($parsed) || ! is_int($parsed)) {
            throw ValidationException::withMessages([
                'amount_rials' => 'مبلغ باید عدد صحیح ریال باشد.',
            ]);
        }

        return self::assertRange($parsed);
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

    private static function assertRange(int $rials): int
    {
        if ($rials < 0 || $rials > 9_000_000_000_000_000) {
            throw ValidationException::withMessages([
                'amount_rials' => 'مبلغ خارج از محدوده مجاز است.',
            ]);
        }

        return $rials;
    }
}
