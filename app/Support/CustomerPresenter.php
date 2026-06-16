<?php

namespace App\Support;

use App\Models\Customer;

class CustomerPresenter
{
    /** @var array<string, string> */
    private const TREND_LABELS = [
        'improving' => 'رو به بهبود',
        'declining' => 'رو به کاهش',
        'stable' => 'پایدار',
    ];

    /** @var array<string, string> */
    private const CONCERN_LABELS = [
        'price' => 'قیمت',
        'trust' => 'اعتماد',
        'timing' => 'زمان‌بندی',
        'technical' => 'فنی',
        'other' => 'سایر',
    ];

    public static function trendLabel(?string $trend): string
    {
        return self::TREND_LABELS[$trend] ?? ($trend ?: '—');
    }

    public static function trendBadgeClass(?string $trend): string
    {
        return match ($trend) {
            'improving' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
            'declining' => 'bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-300',
            'stable' => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
            default => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400',
        };
    }

    public static function leadBadgeClass(?string $level): string
    {
        return match ($level) {
            'high' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
            'medium' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
            'low' => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
            default => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400',
        };
    }

    public static function answerRate(Customer $customer): ?int
    {
        if ($customer->total_calls <= 0) {
            return null;
        }

        return (int) round(($customer->total_answered_calls / $customer->total_calls) * 100);
    }

    public static function concernLabel(string $type): string
    {
        return self::CONCERN_LABELS[$type] ?? $type;
    }

    /** @return list<array{type: string, label: string, count: int}> */
    public static function topConcerns(Customer $customer, int $limit = 3): array
    {
        return collect($customer->common_concerns_json ?? [])
            ->sortByDesc('count')
            ->take($limit)
            ->map(fn (array $concern) => [
                'type' => $concern['type'] ?? 'other',
                'label' => self::concernLabel($concern['type'] ?? 'other'),
                'count' => (int) ($concern['count'] ?? 0),
            ])
            ->values()
            ->all();
    }

    public static function listSubtitle(Customer $customer): string
    {
        return collect([
            $customer->company_name,
            $customer->phone_number,
        ])->filter()->implode(' · ') ?: '—';
    }

    public static function subtitle(Customer $customer): string
    {
        return collect([
            $customer->company_name,
            $customer->job_title,
            $customer->phone_number,
        ])->filter()->implode(' · ') ?: '—';
    }
}
