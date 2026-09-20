<?php

namespace App\Support;

class ForgottenCallbackMatcher
{
    /**
     * True only when the action is a promised phone callback to the customer.
     */
    public static function matches(string $action): bool
    {
        $text = self::normalize($action);

        if ($text === '') {
            return false;
        }

        if (self::isNegativeCallback($text) || self::isInternalHandoff($text)) {
            return false;
        }

        if (! self::hasPhoneCallbackIntent($text)) {
            return false;
        }

        return ! self::callbackIsViaNonPhoneChannel($text);
    }

    private static function normalize(string $action): string
    {
        $text = trim($action);
        $text = str_replace(["\u{200C}", '‌', 'ـ'], ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private static function hasPhoneCallbackIntent(string $text): bool
    {
        $text = preg_replace('/شماره\s*تماس/u', '', $text) ?? $text;

        return self::containsAny($text, [
            'تماس پیگیری',
            'تماس مجدد',
            'تماس برگشت',
            'تماس بازخورد',
            'تماس دوباره',
            'دوباره تماس',
            'تماس با مشتری',
            'پیگیری تلفن',
            'زنگ زدن',
            'زنگ بزن',
            'تماس بگیر',
        ])
            || (bool) preg_match('/دوباره.{0,12}تماس/u', $text)
            || (bool) preg_match(
                '/تماس\s*(?:فردا|پس\s*فردا|امروز|هفته|شنبه|یکشنبه|دوشنبه|سه\s*شنبه|چهارشنبه|پنج\s*شنبه|جمعه|در\s*روز\s*بعد|چک[\s\-]*لیست)/u',
                $text,
            );
    }

    private static function isNegativeCallback(string $text): bool
    {
        return (bool) preg_match('/تماس\s*ن(?:گیر|کن)|زنگ\s*نزن/u', $text);
    }

    private static function isInternalHandoff(string $text): bool
    {
        return self::containsAny($text, [
            'تماس با واحد',
            'تماس داخلی',
            'اطلاع رسانی به واحد',
            'اطلاع‌رسانی به واحد',
            'اطلاع رسانی به سرپرست',
            'اطلاع‌رسانی به سرپرست',
        ]);
    }

    private static function callbackIsViaNonPhoneChannel(string $text): bool
    {
        return (bool) preg_match(
            '/تماس\s*(?:پیگیری|مجدد)?.{0,16}(?:از\s*طریق|در|با|داخل)\s*(?:واتساپ|واتس\s*اپ|تلگرام|اینستاگرام|پیامک|ایمیل|شبکه\s*اجتماعی)/u',
            $text,
        );
    }

    /** @param  list<string>  $needles */
    private static function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && mb_strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
