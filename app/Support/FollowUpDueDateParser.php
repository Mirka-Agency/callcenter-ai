<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;

class FollowUpDueDateParser
{
    /**
     * Resolve a follow-up due date from AI-generated Persian action text.
     */
    public static function parse(string $action, DateTimeInterface $from): ?Carbon
    {
        $base = $from instanceof CarbonInterface
            ? $from->copy()
            : Carbon::parse($from);

        $text = self::normalize($action);

        if ($text === '') {
            return null;
        }

        $explicit = self::explicitDate($text);

        if ($explicit !== null) {
            return $explicit;
        }

        if (preg_match('/(\d+)\s*روز\s*(?:دیگر|بعد|آینده)/u', $text, $match)) {
            return $base->copy()->addDays((int) $match[1])->startOfDay();
        }

        if (preg_match('/پس[\s\-]*فردا/u', $text)) {
            return $base->copy()->addDays(2)->startOfDay();
        }

        if (preg_match('/فردا|روز\s*بعد/u', $text)) {
            return $base->copy()->addDay()->startOfDay();
        }

        if (preg_match('/امروز|همین\s*روز|در\s*همین\s*روز/u', $text)) {
            return $base->copy()->startOfDay();
        }

        if (preg_match('/دو\s*هفته/u', $text)) {
            return $base->copy()->addWeeks(2)->startOfDay();
        }

        if (preg_match('/(\d+)\s*هفته\s*(?:دیگر|بعد|آینده)/u', $text, $match)) {
            return $base->copy()->addWeeks((int) $match[1])->startOfDay();
        }

        if (preg_match('/هفته\s*(?:آینده|بعد|دیگر)/u', $text)) {
            return $base->copy()->addWeek()->startOfDay();
        }

        if (preg_match('/ماه\s*(?:آینده|بعد|دیگر)/u', $text)) {
            return $base->copy()->addMonth()->startOfDay();
        }

        return self::weekdayDueDate($text, $base);
    }

    public static function parseOrDefault(string $action, DateTimeInterface $from): Carbon
    {
        $parsed = self::parse($action, $from);

        if ($parsed !== null) {
            return $parsed;
        }

        $base = $from instanceof CarbonInterface
            ? $from->copy()
            : Carbon::parse($from);

        return $base->addDay()->startOfDay();
    }

    private static function normalize(string $action): string
    {
        $text = PersianNumber::toLatinDigits(trim($action));
        $text = str_replace(["\u{200C}", '‌', 'ـ'], ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private static function explicitDate(string $text): ?Carbon
    {
        if (preg_match('/(13|14)\d{2}[\/\-](1[0-2]|0?[1-9])[\/\-](3[01]|[12]\d|0?[1-9])/u', $text, $match)) {
            return JalaliDate::toGregorian(str_replace('-', '/', $match[0]))?->startOfDay();
        }

        if (preg_match('/20\d{2}-\d{2}-\d{2}/u', $text, $match)) {
            try {
                return Carbon::parse($match[0])->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private static function weekdayDueDate(string $text, CarbonInterface $from): ?Carbon
    {
        $weekdays = [
            'یکشنبه' => Carbon::SUNDAY,
            'پنج شنبه' => Carbon::THURSDAY,
            'پنجشنبه' => Carbon::THURSDAY,
            'چهارشنبه' => Carbon::WEDNESDAY,
            'سه شنبه' => Carbon::TUESDAY,
            'دوشنبه' => Carbon::MONDAY,
            'جمعه' => Carbon::FRIDAY,
            'شنبه' => Carbon::SATURDAY,
        ];

        foreach ($weekdays as $label => $dayOfWeek) {
            if (! str_contains($text, $label)) {
                continue;
            }

            if ((int) $from->dayOfWeek === $dayOfWeek) {
                return $from->copy()->startOfDay();
            }

            return $from->copy()->next($dayOfWeek)->startOfDay();
        }

        return null;
    }
}
