<?php

namespace App\Services\Performance\Coaching;

class CoachingRecommendationBuilder
{
    /**
     * @param  list<string>  $weaknesses
     * @return array{training_areas: list<string>, coaching_plan: list<string>}
     */
    public function build(array $weaknesses): array
    {
        $training = collect($weaknesses)->map(fn (string $weakness) => match (true) {
            str_contains($weakness, 'پیگیری') || str_contains($weakness, 'follow') => 'تمرکز بر پیگیری و بستن تماس',
            str_contains($weakness, 'قطع') || str_contains($weakness, 'interrupt') => 'کاهش قطع کردن مشتری',
            str_contains($weakness, 'کشف') || str_contains($weakness, 'discovery') => 'بهبود سوالات کشف نیاز',
            str_contains($weakness, 'اعتراض') || str_contains($weakness, 'objection') => 'تمرین مدیریت اعتراضات',
            default => 'تمرکز بر '.$weakness,
        })->unique()->take(4)->values()->all();

        $plan = [
            'مرور هفتگی ضعف‌های پرتکرار در مکالمات',
            ...array_slice($training, 0, 3),
            'شنیدن ۲ مکالمه موفق و ۲ مکالمه ضعیف هفته برای الگوبرداری',
        ];

        return [
            'training_areas' => $training,
            'coaching_plan' => array_values(array_unique($plan)),
        ];
    }
}
