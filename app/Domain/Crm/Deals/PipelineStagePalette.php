<?php

namespace App\Domain\Crm\Deals;

use Illuminate\Validation\ValidationException;

final class PipelineStagePalette
{
    /** @var list<string> */
    public const ALLOWED = [
        '#6366F1',
        '#7C25FD',
        '#D97706',
        '#059669',
        '#E11D48',
        '#71717A',
    ];

    public const KIND_SALES = 'sales';

    public const KIND_SUPPORT = 'support';

    /** @var list<array{name: string, color: string}> */
    public const DEFAULT_STAGES = [
        ['name' => 'سرنخ جدید', 'color' => '#6366F1'],
        ['name' => 'نیازسنجی', 'color' => '#7C25FD'],
        ['name' => 'ارائه پیشنهاد', 'color' => '#D97706'],
        ['name' => 'مذاکره', 'color' => '#059669'],
    ];

    /** @var list<array{name: string, color: string}> */
    public const SUPPORT_STAGES = [
        ['name' => 'تیکت جدید', 'color' => '#6366F1'],
        ['name' => 'در حال پیگیری', 'color' => '#7C25FD'],
        ['name' => 'در انتظار مشتری', 'color' => '#D97706'],
        ['name' => 'آماده بستن', 'color' => '#059669'],
    ];

    public static function normalize(string $color): string
    {
        $color = strtoupper(trim($color));

        foreach (self::ALLOWED as $allowed) {
            if (strtoupper($allowed) === $color) {
                return $allowed;
            }
        }

        throw ValidationException::withMessages([
            'color' => 'رنگ مرحله باید از رنگ‌های مجاز سامانه باشد.',
        ]);
    }

    public static function colorForPosition(int $position): string
    {
        $palette = self::ALLOWED;
        $index = max(0, $position - 1);

        return $palette[$index % count($palette)];
    }

    public static function normalizeKind(?string $kind): string
    {
        return $kind === self::KIND_SUPPORT ? self::KIND_SUPPORT : self::KIND_SALES;
    }

    public static function defaultName(string $kind): string
    {
        return self::normalizeKind($kind) === self::KIND_SUPPORT ? 'پشتیبانی' : 'فروش';
    }

    /** @return list<array{name: string, color: string}> */
    public static function stagesForKind(?string $kind): array
    {
        return self::normalizeKind($kind) === self::KIND_SUPPORT
            ? self::SUPPORT_STAGES
            : self::DEFAULT_STAGES;
    }
}
