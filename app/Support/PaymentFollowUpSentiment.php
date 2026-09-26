<?php

namespace App\Support;

use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Models\ConversationAnalysis;

/**
 * Outbound collection calls are easy to invert: the employee asks for unpaid
 * money, and the model treats that demand as the customer's complaint.
 * Those calls must stay out of the dissatisfied-customer list unless the
 * customer also objects to the brand, product, or service.
 */
class PaymentFollowUpSentiment
{
    /** @var list<string> */
    private const COLLECTION_PATTERNS = [
        'پرداخت[\s\x{200C}]*نشد',
        'پرداخت[\s\x{200C}]*نکرد',
        'عدم[\s\x{200C}]*پرداخت',
        'پول[\s\x{200C}]*(?:را[\s\x{200C}]*)?(?:نداد|نپرداخت)',
        'بدهی',
        'وصول[\s\x{200C}]*مطالب',
        'فاکتور[\s\x{200C}]*معوق',
        'تسویه[\s\x{200C}]*نشد',
        'واریز[\s\x{200C}]*نشد',
        'واریز[\s\x{200C}]*نکرد',
        'پیگیری[\s\x{200C}]*پرداخت',
        'پیگیری[\s\x{200C}]*بدهی',
    ];

    /** @var list<string> */
    private const COMPLAINT_PATTERNS = [
        'محصول[\s\x{200C}]*معیوب',
        'کیفیت[\s\x{200C}]*محصول',
        'کیفیت[\s\x{200C}]*خدمات',
        'کیفیت[\s\x{200C}]*پایین',
        'نارضایتی[\s\x{200C}]*از[\s\x{200C}]*(?:محصول|خدمات|سرویس|برند|کیفیت)',
        'اعتراض[\s\x{200C}]*به[\s\x{200C}]*(?:محصول|خدمات|سرویس|برند)',
        'شکایت[\s\x{200C}]*از[\s\x{200C}]*(?:محصول|خدمات|سرویس|برند|پشتیبانی)',
        'قطع[\s\x{200C}]*همکاری',
        'قطع[\s\x{200C}]*سرویس',
        'قطع[\s\x{200C}]*خدمات',
        'فاکتور[\s\x{200C}]*اشتباه',
        'اضافه[\s\x{200C}]*حساب',
        'بیشتر[\s\x{200C}]*از[\s\x{200C}]*توافق',
        'بازپرداخت',
        'بدقولی',
        'خرابی',
        'تأخیر[\s\x{200C}]*در[\s\x{200C}]*ارسال',
        'تأخیر[\s\x{200C}]*در[\s\x{200C}]*تحویل',
    ];

    /** @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    public static function correct(array $response): array
    {
        if (! self::responseLooksMisclassified($response)) {
            return $response;
        }

        $response['sentiment'] = AnalysisSentiment::Neutral->value;

        if (is_array($response['customer_insights'] ?? null)) {
            $response['customer_insights']['sentiment'] = AnalysisSentiment::Neutral->value;
        }

        $response['concerns'] = [];
        $response['needs_attention'] = [
            'needed' => false,
            'categories' => [],
            'reason' => '',
        ];

        return $response;
    }

    /** @param  array<string, mixed>  $response */
    public static function responseLooksMisclassified(array $response): bool
    {
        $sentiment = mb_strtolower(trim((string) ($response['sentiment'] ?? '')));
        $insights = is_array($response['customer_insights'] ?? null) ? $response['customer_insights'] : [];
        $insightSentiment = mb_strtolower(trim((string) ($insights['sentiment'] ?? '')));

        $negative = in_array($sentiment, ['negative', 'mixed', 'منفی', 'ترکیبی'], true)
            || in_array($insightSentiment, ['negative', 'mixed', 'منفی', 'ترکیبی'], true);

        if (! $negative) {
            return false;
        }

        return self::isCollectionWithoutComplaint(self::corpusFromResponse($response));
    }

    public static function analysisLooksMisclassified(ConversationAnalysis $analysis): bool
    {
        if (! in_array($analysis->sentiment, [AnalysisSentiment::Negative, AnalysisSentiment::Mixed], true)) {
            return false;
        }

        return self::isCollectionWithoutComplaint(self::corpusFromAnalysis($analysis));
    }

    public static function isCollectionWithoutComplaint(string $text): bool
    {
        $text = trim($text);

        if ($text === '') {
            return false;
        }

        return self::matchesAny($text, self::COLLECTION_PATTERNS)
            && ! self::matchesAny($text, self::COMPLAINT_PATTERNS);
    }

    /** @param  array<string, mixed>  $response */
    private static function corpusFromResponse(array $response): string
    {
        $parts = [
            (string) ($response['summary'] ?? ''),
            (string) ($response['overall_evaluation'] ?? ''),
        ];

        $insights = is_array($response['customer_insights'] ?? null) ? $response['customer_insights'] : [];
        $parts[] = (string) ($insights['intent'] ?? '');

        $attention = is_array($response['needs_attention'] ?? null) ? $response['needs_attention'] : [];
        $parts[] = (string) ($attention['reason'] ?? '');

        foreach (is_array($response['concerns'] ?? null) ? $response['concerns'] : [] as $concern) {
            if (is_array($concern)) {
                $parts[] = (string) ($concern['text'] ?? '');
            }
        }

        return implode("\n", $parts);
    }

    private static function corpusFromAnalysis(ConversationAnalysis $analysis): string
    {
        $insights = is_array($analysis->customer_insights_json) ? $analysis->customer_insights_json : [];
        $attention = is_array($analysis->attention_json) ? $analysis->attention_json : [];
        $parts = [
            (string) $analysis->summary,
            (string) $analysis->overall_evaluation,
            (string) ($insights['intent'] ?? ''),
            (string) ($attention['reason'] ?? ''),
        ];

        foreach (is_array($analysis->concerns_json) ? $analysis->concerns_json : [] as $concern) {
            if (is_array($concern)) {
                $parts[] = (string) ($concern['text'] ?? '');
            }
        }

        return implode("\n", $parts);
    }

    /** @param  list<string>  $patterns */
    private static function matchesAny(string $text, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match('/'.$pattern.'/u', $text) === 1) {
                return true;
            }
        }

        return false;
    }
}
