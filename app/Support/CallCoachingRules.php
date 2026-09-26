<?php

namespace App\Support;

class CallCoachingRules
{
    public const REDIRECT_FOLLOW_UP = 'تماس پیگیری ۳ روز دیگر برای اتصال به بخش یا داخلی معرفی‌شده';

    /** @param  array<string, mixed>  $response */
    public static function responseLacksConversation(array $response): bool
    {
        return self::describesUnconnectedCall(implode("\n", [
            (string) ($response['summary'] ?? ''),
            (string) ($response['overall_evaluation'] ?? $response['evaluation'] ?? ''),
        ]));
    }

    /**
     * A call that never became a two-way conversation must not produce coaching.
     *
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    public static function clearUnevaluableCoaching(array $response): array
    {
        $response['weaknesses'] = [];
        $response['strengths'] = [];
        $response['next_actions'] = [];
        $response['concerns'] = [];
        $response['performance_dimensions'] = [];

        $operational = is_array($response['operational_insights'] ?? null)
            ? $response['operational_insights']
            : [];
        $operational['follow_up_suggestions'] = [];
        $operational['missed_opportunities'] = [];
        $response['operational_insights'] = $operational;
        $response['needs_attention'] = [
            'needed' => false,
            'categories' => [],
            'reason' => '',
        ];

        return $response;
    }

    /**
     * Being told to call another extension is a later callback, not a weakness of this call.
     *
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    public static function moveRedirectsToFollowUp(array $response): array
    {
        $moved = false;
        $response['weaknesses'] = self::withoutRedirects($response['weaknesses'] ?? [], $moved);
        $response['next_actions'] = self::withoutRedirects($response['next_actions'] ?? [], $moved);

        $operational = is_array($response['operational_insights'] ?? null)
            ? $response['operational_insights']
            : [];
        $operational['missed_opportunities'] = self::withoutRedirects(
            $operational['missed_opportunities'] ?? [],
            $moved,
        );

        $suggestions = is_array($operational['follow_up_suggestions'] ?? null)
            ? $operational['follow_up_suggestions']
            : [];

        if ($moved && ! self::listContains($suggestions, self::REDIRECT_FOLLOW_UP)) {
            $suggestions[] = self::REDIRECT_FOLLOW_UP;
        }

        $operational['follow_up_suggestions'] = array_values($suggestions);
        $response['operational_insights'] = $operational;

        return $response;
    }

    public static function shouldHideWeakness(string $weakness, string $summary): bool
    {
        if (self::isDeferredRedirectFollowUp($weakness)) {
            return true;
        }

        if (! self::isNoConversationWeakness($weakness)) {
            return false;
        }

        return self::describesUnconnectedCall($summary) || self::describesUnconnectedCall($weakness);
    }

    public static function isDeferredRedirectFollowUp(string $text): bool
    {
        $text = self::normalize($text);

        if ($text === self::normalize(self::REDIRECT_FOLLOW_UP)) {
            return true;
        }
        $mentionsDestination = self::containsAny($text, [
            'داخلی',
            'بخش مربوط',
            'بخش دیگر',
            'واحد مربوط',
            'اتصال مستقیم',
            'انتقال به بخش',
            'وصل به بخش',
        ]);

        if (! $mentionsDestination) {
            return false;
        }

        return self::containsAny($text, [
            'عدم پیگیری',
            'پیگیری نشد',
            'پیگیری نکرد',
            'دنبال نکرد',
            'متصل نشد',
            'وصل نشد',
            'اتصال انجام نشد',
        ]);
    }

    public static function describesUnconnectedCall(string $text): bool
    {
        $text = self::normalize($text);

        if ($text === '') {
            return false;
        }

        return self::containsAny($text, [
            'مکالمه شکل نگرفت',
            'مکالمه ای شکل نگرفت',
            'بدون مکالمه',
            'تماس برقرار نشد',
            'ارتباط برقرار نشد',
            'مشتری پاسخ نداد',
            'مشتری جواب نداد',
            'طرف مقابل پاسخ نداد',
            'طرف مقابل جواب نداد',
            'مشتری پاسخگو نبود',
            'فقط بوق',
            'پیام اپراتور',
            'صندوق صوتی',
            'قابل ارزیابی نبود',
            'صحبت معناداری نشد',
            'مکالمه معناداری رخ نداد',
            'مکالمه واقعی نبود',
        ]);
    }

    /** @param  list<mixed>  $items */
    private static function withoutRedirects(mixed $items, bool &$moved): array
    {
        if (! is_array($items)) {
            return [];
        }

        $kept = [];

        foreach ($items as $item) {
            $text = self::itemText($item);

            if ($text !== null && self::isDeferredRedirectFollowUp($text)) {
                $moved = true;

                continue;
            }

            $kept[] = $item;
        }

        return array_values($kept);
    }

    /** @param  list<mixed>  $items */
    private static function listContains(array $items, string $needle): bool
    {
        foreach ($items as $item) {
            if (self::itemText($item) === $needle) {
                return true;
            }
        }

        return false;
    }

    private static function isNoConversationWeakness(string $weakness): bool
    {
        return self::containsAny(self::normalize($weakness), [
            'عدم ارتباط با مشتری',
            'عدم پاسخ',
            'پاسخ نداد',
            'جواب نداد',
            'برقرار نشد',
            'بدون مکالمه',
            'مکالمه شکل نگرفت',
            'مکالمه ای شکل نگرفت',
        ]);
    }

    private static function itemText(mixed $item): ?string
    {
        if (is_string($item)) {
            $text = trim($item);

            return $text !== '' ? $text : null;
        }

        if (! is_array($item)) {
            return null;
        }

        foreach (['text', 'title', 'weakness', 'description', 'label', 'action'] as $key) {
            if (! empty($item[$key]) && is_string($item[$key])) {
                return trim($item[$key]);
            }
        }

        return null;
    }

    private static function normalize(string $text): string
    {
        $text = str_replace(["\u{200C}", '‌', 'ـ'], ' ', trim($text));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /** @param  list<string>  $needles */
    private static function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            $needle = self::normalize($needle);

            if ($needle !== '' && mb_strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
