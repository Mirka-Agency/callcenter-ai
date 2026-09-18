<?php

namespace App\Support;

use App\Models\ConversationAnalysis;

class NeedsAttention
{
    /** @var list<string> */
    public const CATEGORIES = ['agent', 'product', 'service', 'general', 'other'];

    /**
     * @param  array<string, mixed>  $response
     * @return array{needed: bool, categories: list<string>, reason: string}
     */
    public static function fromResponse(array $response): array
    {
        if (array_key_exists('needs_attention', $response) || array_key_exists('attention', $response)) {
            return self::normalize($response['needs_attention'] ?? $response['attention']);
        }

        return self::inferFromResponse($response);
    }

    /**
     * @return array{needed: bool, categories: list<string>, reason: string}
     */
    public static function normalize(mixed $payload): array
    {
        if (! is_array($payload)) {
            if (! self::toBool($payload)) {
                return self::empty();
            }

            return [
                'needed' => true,
                'categories' => ['general'],
                'reason' => 'در این تماس داده مهمی برای پیگیری مدیریت تشخیص داده شد.',
            ];
        }

        $needed = self::toBool($payload['needed'] ?? $payload['flagged'] ?? $payload['required'] ?? false);
        $reason = trim((string) ($payload['reason'] ?? $payload['text'] ?? ''));
        $categories = self::normalizeCategories($payload['categories'] ?? $payload['category'] ?? []);

        if (! $needed && ($reason !== '' || $categories !== [])) {
            $needed = true;
        }

        if (! $needed) {
            return self::empty();
        }

        if ($categories === []) {
            $categories = self::categoriesFromText($reason);
        }

        if ($categories === []) {
            $categories = ['general'];
        }

        if ($reason === '') {
            $reason = 'در این تماس داده مهمی برای پیگیری مدیریت تشخیص داده شد.';
        }

        return [
            'needed' => true,
            'categories' => $categories,
            'reason' => $reason,
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array{needed: bool, categories: list<string>, reason: string}
     */
    public static function inferFromResponse(array $response): array
    {
        $sentiment = $response['sentiment'] ?? $response['customer_insights']['sentiment'] ?? null;
        $customerInsights = is_array($response['customer_insights'] ?? null) ? $response['customer_insights'] : [];
        $operational = is_array($response['operational_insights'] ?? null) ? $response['operational_insights'] : [];
        $concerns = is_array($response['concerns'] ?? null) ? $response['concerns'] : [];
        $summary = (string) ($response['summary'] ?? '');

        return self::inferFromSignals(
            is_string($sentiment) ? $sentiment : null,
            $customerInsights,
            $operational,
            $concerns,
            $summary,
        );
    }

    /**
     * @return array{needed: bool, categories: list<string>, reason: string}
     */
    public static function inferFromAnalysis(ConversationAnalysis $analysis): array
    {
        return self::inferFromSignals(
            $analysis->sentiment?->value,
            is_array($analysis->customer_insights_json) ? $analysis->customer_insights_json : [],
            is_array($analysis->operational_insights_json) ? $analysis->operational_insights_json : [],
            is_array($analysis->concerns_json) ? $analysis->concerns_json : [],
            (string) $analysis->summary,
        );
    }

    /**
     * @param  array<string, mixed>  $customerInsights
     * @param  array<string, mixed>  $operationalInsights
     * @param  list<mixed>  $concerns
     * @return array{needed: bool, categories: list<string>, reason: string}
     */
    public static function inferFromSignals(
        ?string $sentiment,
        array $customerInsights,
        array $operationalInsights,
        array $concerns,
        string $summary = '',
    ): array {
        $escalationRisks = self::stringList($operationalInsights['escalation_risks'] ?? []);
        $complianceIssues = self::stringList($operationalInsights['compliance_issues'] ?? []);
        $riskLevel = strtolower(trim((string) ($customerInsights['risk_level'] ?? '')));
        $concernTexts = [];
        $hasHighSeverityConcern = false;
        $hasTrustConcern = false;

        foreach ($concerns as $concern) {
            if (! is_array($concern)) {
                continue;
            }

            $text = trim((string) ($concern['text'] ?? ''));
            if ($text !== '') {
                $concernTexts[] = $text;
            }

            $severity = strtolower((string) ($concern['severity'] ?? ''));
            $type = strtolower((string) ($concern['type'] ?? ''));

            if ($severity === 'high') {
                $hasHighSeverityConcern = true;
            }

            if ($type === 'trust') {
                $hasTrustConcern = true;
            }
        }

        $haystack = trim(implode(' ', array_filter([
            $summary,
            ...$concernTexts,
            ...$escalationRisks,
            ...$complianceIssues,
        ])));

        $hasComplaintLanguage = self::containsComplaintKeyword($haystack);
        $needed = $hasComplaintLanguage
            || $escalationRisks !== []
            || $complianceIssues !== []
            || $riskLevel === 'high'
            || ($sentiment === 'negative' && ($hasHighSeverityConcern || $hasTrustConcern));

        if (! $needed) {
            return self::empty();
        }

        $categories = self::categoriesFromText($haystack);
        if ($categories === []) {
            $categories = $hasComplaintLanguage ? ['general'] : ['other'];
        }

        $reason = $concernTexts[0]
            ?? $escalationRisks[0]
            ?? $complianceIssues[0]
            ?? ($hasComplaintLanguage
                ? 'مشتری در این تماس اعتراض یا نارضایتی مطرح کرده است.'
                : 'ریسک یا سیگنال مهمی برای پیگیری مدیریت تشخیص داده شد.');

        return [
            'needed' => true,
            'categories' => $categories,
            'reason' => $reason,
        ];
    }

    public static function categoryLabel(string $category): string
    {
        return match ($category) {
            'agent' => 'عملکرد کارشناس',
            'product' => 'محصول',
            'service' => 'سرویس',
            'general' => 'اعتراض کلی',
            default => 'سایر',
        };
    }

    /**
     * @return array{needed: bool, categories: list<string>, reason: string}
     */
    public static function empty(): array
    {
        return [
            'needed' => false,
            'categories' => [],
            'reason' => '',
        ];
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return in_array(mb_strtolower(trim($value)), ['1', 'true', 'yes', 'بله', 'درست'], true);
        }

        return false;
    }

    /** @return list<string> */
    private static function normalizeCategories(mixed $categories): array
    {
        if (is_string($categories) && trim($categories) !== '') {
            $categories = [$categories];
        }

        if (! is_array($categories)) {
            return [];
        }

        $normalized = [];

        foreach ($categories as $category) {
            $value = self::normalizeCategory($category);

            if ($value !== null && ! in_array($value, $normalized, true)) {
                $normalized[] = $value;
            }
        }

        return $normalized;
    }

    private static function normalizeCategory(mixed $value): ?string
    {
        $normalized = mb_strtolower(trim((string) $value));

        return match ($normalized) {
            'agent', 'کارشناس', 'عملکرد کارشناس', 'عملکرد' => 'agent',
            'product', 'محصول', 'کالا' => 'product',
            'service', 'سرویس', 'خدمات', 'پشتیبانی' => 'service',
            'general', 'کلی', 'اعتراض', 'اعتراض کلی' => 'general',
            'other', 'سایر' => 'other',
            default => null,
        };
    }

    /** @return list<string> */
    private static function categoriesFromText(string $text): array
    {
        $categories = [];

        if (self::containsAny($text, ['کارشناس', 'عملکرد'])) {
            $categories[] = 'agent';
        }

        if (self::containsAny($text, ['محصول', 'کالا'])) {
            $categories[] = 'product';
        }

        if (self::containsAny($text, ['سرویس', 'خدمات', 'پشتیبانی'])) {
            $categories[] = 'service';
        }

        if ($categories === [] && self::containsComplaintKeyword($text)) {
            $categories[] = 'general';
        }

        return $categories;
    }

    private static function containsComplaintKeyword(string $text): bool
    {
        return self::containsAny($text, [
            'اعتراض',
            'شکایت',
            'ناراضی',
            'نارضایتی',
            'گله',
            'قطع همکاری',
            'مدیرتون',
        ]);
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

    /** @return list<string> */
    private static function stringList(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter(
            $items,
            fn (mixed $item) => is_string($item) && trim($item) !== '',
        ));
    }
}
