<?php

namespace App\Support;

use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Models\ConversationAnalysis;

class AnalysisInsightPresenter
{
    /** @var array<string, string> */
    private const DIMENSION_LABELS = [
        'communication_skills' => 'مهارت ارتباطی',
        'product_knowledge' => 'دانش محصول',
        'objection_handling' => 'مدیریت اعتراض',
        'closing_ability' => 'توان بستن معامله',
        'professionalism' => 'حرفه‌ای‌گری',
    ];

    public static function dimensionLabel(string $key): string
    {
        return self::DIMENSION_LABELS[$key] ?? str($key)->replace('_', ' ')->title()->toString();
    }

    public static function dimensionScore(mixed $value): int
    {
        if (is_array($value)) {
            return (int) ($value['score'] ?? 0);
        }

        return (int) $value;
    }

    public static function scoreTextClass(int $score): string
    {
        return match (true) {
            $score >= 85 => 'text-emerald-600 dark:text-emerald-400',
            $score >= 70 => 'text-amber-600 dark:text-amber-400',
            default => 'text-red-600 dark:text-red-400',
        };
    }

    public static function scoreRingClass(int $score): string
    {
        return match (true) {
            $score >= 85 => 'from-emerald-500 to-emerald-600',
            $score >= 70 => 'from-amber-500 to-amber-600',
            default => 'from-red-500 to-red-600',
        };
    }

    public static function sentimentBadgeClass(AnalysisSentiment $sentiment): string
    {
        return match ($sentiment->color()) {
            'success' => 'saas-badge-success',
            'danger' => 'saas-badge-danger',
            'warning' => 'saas-badge-warning',
            default => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
        };
    }

    public static function leadLevelLabel(?string $level): string
    {
        return match ($level) {
            'high' => 'بالا',
            'medium' => 'متوسط',
            'low' => 'کم',
            default => $level ?? '—',
        };
    }

    public static function leadLevelClass(?string $level): string
    {
        return match ($level) {
            'high' => 'text-emerald-600 dark:text-emerald-400',
            'medium' => 'text-amber-600 dark:text-amber-400',
            'low' => 'text-zinc-500',
            default => 'text-zinc-600',
        };
    }

    public static function customerName(ConversationAnalysis $analysis): ?string
    {
        $identity = $analysis->customer_identity_json ?? [];

        $identityName = trim((string) ($identity['person_name'] ?? ''));

        if ($identityName !== '') {
            return $identityName;
        }

        $callName = trim((string) ($analysis->call?->customer_name ?? ''));

        if ($callName !== '') {
            return $callName;
        }

        $linkedCustomerName = trim((string) ($analysis->call?->customer?->displayName() ?? ''));

        return $linkedCustomerName !== '' ? $linkedCustomerName : null;
    }

    public static function customerInsightLabel(string $key): string
    {
        return match ($key) {
            'sentiment' => 'احساس مشتری',
            'intent' => 'خواسته مشتری',
            'purchase_probability' => 'احتمال خرید',
            'urgency_level' => 'سطح فوریت',
            'risk_level' => 'سطح ریسک',
            default => str($key)->replace('_', ' ')->title()->toString(),
        };
    }

    public static function customerInsightValue(string $key, mixed $value): string
    {
        if ($key === 'purchase_probability' && is_numeric($value)) {
            return number_format((float) $value).'%';
        }

        if (in_array($key, ['urgency_level', 'risk_level'], true) && is_string($value)) {
            return self::levelLabel($value);
        }

        if ($key === 'sentiment' && is_string($value)) {
            return match ($value) {
                'positive' => 'مثبت',
                'negative' => 'منفی',
                'neutral' => 'خنثی',
                'mixed' => 'مختلط',
                default => $value,
            };
        }

        return is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    public static function concernTypeLabel(?string $type): string
    {
        return match ($type) {
            'price' => 'قیمت',
            'trust' => 'اعتماد',
            'timing' => 'زمان‌بندی',
            'technical' => 'فنی',
            default => 'سایر',
        };
    }

    public static function severityLabel(?string $severity): string
    {
        return self::levelLabel($severity ?? 'medium');
    }

    public static function severityBadgeClass(?string $severity): string
    {
        return match ($severity) {
            'low' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300',
            'high' => 'bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-300',
            default => 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300',
        };
    }

    private static function levelLabel(?string $level): string
    {
        return match ($level) {
            'low' => 'کم',
            'medium' => 'متوسط',
            'high' => 'بالا',
            'critical' => 'بحرانی',
            default => $level ?? '—',
        };
    }
}
