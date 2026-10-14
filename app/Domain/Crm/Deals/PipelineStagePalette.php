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

    /** @var list<array{name: string, color: string}> */
    public const DEFAULT_STAGES = [
        ['name' => 'سرنخ جدید', 'color' => '#6366F1'],
        ['name' => 'نیازسنجی', 'color' => '#7C25FD'],
        ['name' => 'ارائه پیشنهاد', 'color' => '#D97706'],
        ['name' => 'مذاکره', 'color' => '#059669'],
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
}
