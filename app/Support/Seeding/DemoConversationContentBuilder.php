<?php

namespace App\Support\Seeding;

use App\Application\Llm\Services\PromptBuilder;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Models\Customer;
use App\Models\OrganizationUser;
use App\Support\NeedsAttention;
use Faker\Generator;
use Illuminate\Support\Arr;

/**
 * Generates realistic Persian call transcripts and analysis payloads
 * aligned with {@see PromptBuilder} output shape.
 */
final class DemoConversationContentBuilder
{
    /**
     * @return array{
     *     transcript: string,
     *     summary: string,
     *     overall_evaluation: string,
     *     strengths_json: list<string>,
     *     weaknesses_json: list<string>,
     *     next_actions_json: list<string>,
     *     performance_dimensions_json: array<string, array{score: int}>,
     *     customer_insights_json: array<string, mixed>,
     *     operational_insights_json: array<string, list<string>>,
     *     lead_quality_json: array<string, mixed>,
     *     concerns_json: list<array<string, string>>,
     *     needs_attention: bool,
     *     attention_json: array{needed: bool, categories: list<string>, reason: string},
     *     customer_identity_json: array<string, mixed>,
     *     call_status: string,
     *     call_category: string,
     *     call_title: string,
     *     call_notes: string,
     *     metadata: array<string, mixed>,
     * }
     */
    public function build(
        int $seed,
        int $score,
        AnalysisSentiment $sentiment,
        OrganizationUser $employee,
        Customer $customer,
        string $direction,
        int $durationSeconds,
        string $organizationTitle,
    ): array {
        $faker = \fake();
        $faker->seed($seed);

        $scenario = DemoCatalog::callScenarios()[$seed % count(DemoCatalog::callScenarios())];
        $agentName = $employee->first_name ?: explode(' ', $employee->full_name)[0];
        $customerLabel = $customer->name ?: $customer->company_name ?: 'مشتری';
        $companyLabel = $customer->company_name ?: $customerLabel;
        $outcome = $this->resolveOutcome($score, $scenario['outcome'], $faker);
        $callStatus = $this->callStatusForOutcome($outcome, $faker);

        $leadScore = $this->leadScoreForScenario($score, $scenario['lead_bias'], $faker);
        $leadLevel = match (true) {
            $leadScore >= 75 => 'high',
            $leadScore >= 45 => 'medium',
            default => 'low',
        };

        $concernSeverity = $score >= 75 ? 'low' : ($score >= 60 ? 'medium' : 'high');
        $urgency = $faker->randomElement(['low', 'medium', 'high', 'critical']);
        $purchaseProbability = min(100, max(5, $leadScore + $faker->numberBetween(-8, 8)));

        $transcript = $this->buildTranscript(
            $scenario,
            $agentName,
            $customerLabel,
            $organizationTitle,
            $direction,
            $durationSeconds,
            $faker,
        );

        $summary = $this->buildSummary(
            $scenario,
            $agentName,
            $customerLabel,
            $companyLabel,
            $organizationTitle,
            $outcome,
            $durationSeconds,
        );

        $strengths = collect(DemoCatalog::strengths())->shuffle()->take($score >= 80 ? 3 : 2)->values()->all();
        $weaknesses = $score < 75
            ? collect(DemoCatalog::weaknesses())->shuffle()->take($score < 60 ? 2 : 1)->values()->all()
            : [];

        $nextActions = $this->nextActionsForOutcome($outcome, $scenario, $faker);

        $concerns = [[
            'type' => $scenario['concern_type'],
            'text' => $scenario['concern_text'],
            'severity' => $concernSeverity,
        ]];

        $customerInsights = [
            'sentiment' => $sentiment->value,
            'intent' => $scenario['intent'],
            'purchase_probability' => $purchaseProbability,
            'urgency_level' => $urgency,
            'risk_level' => $score >= 70 ? 'low' : ($score >= 55 ? 'medium' : 'high'),
        ];

        $operationalInsights = [
            'missed_opportunities' => $score < 72
                ? [Arr::random(['فرصت پیشنهاد بسته مکمل از دست رفت', 'عدم پرسش درباره نیازهای آینده مشتری'])]
                : [],
            'escalation_risks' => $score < 58
                ? ['احتمال شکایت مجدد در صورت عدم پیگیری سریع']
                : [],
            'compliance_issues' => [],
            'important_keywords' => $scenario['keywords'],
            'follow_up_suggestions' => $nextActions,
        ];

        $attention = ! empty($scenario['needs_attention'])
            ? NeedsAttention::normalize([
                'needed' => true,
                'categories' => $scenario['attention_categories'] ?? ['general'],
                'reason' => $scenario['attention_reason'] ?? $scenario['concern_text'],
            ])
            : NeedsAttention::inferFromSignals(
                $sentiment->value,
                $customerInsights,
                $operationalInsights,
                $concerns,
                $summary,
            );

        return [
            'transcript' => $transcript,
            'summary' => $summary,
            'overall_evaluation' => $this->overallEvaluation($score, $outcome),
            'strengths_json' => $strengths,
            'weaknesses_json' => $weaknesses,
            'next_actions_json' => $nextActions,
            'performance_dimensions_json' => $this->performanceDimensions($score, $faker),
            'customer_insights_json' => $customerInsights,
            'operational_insights_json' => $operationalInsights,
            'lead_quality_json' => [
                'score' => $leadScore,
                'level' => $leadLevel,
                'reason' => $scenario['lead_reason'],
                'buying_intent_signals' => $scenario['buying_signals'],
            ],
            'concerns_json' => $concerns,
            'needs_attention' => $attention['needed'],
            'attention_json' => $attention,
            'customer_identity_json' => [
                'person_name' => $customer->name,
                'company_name' => $customer->company_name,
                'confidence' => round($faker->randomFloat(2, 0.72, 0.97), 2),
                'evidence' => "مشتری خود را به نام {$customerLabel} از {$companyLabel} معرفی کرد.",
            ],
            'call_status' => $callStatus,
            'call_category' => $scenario['category'],
            'call_title' => $scenario['title'],
            'call_notes' => $scenario['notes'],
            'metadata' => [
                'demo_scenario' => $scenario['key'],
                'outcome' => $outcome,
                'outcome_label' => DemoCatalog::callOutcomeLabel($outcome),
                'direction' => $direction,
                'duration_seconds' => $durationSeconds,
                'prompt_version' => 'v1',
                'generated_by' => 'demo_seeder',
            ],
        ];
    }

    private function resolveOutcome(int $score, string $preferredOutcome, Generator $faker): string
    {
        if ($score < 52 && $faker->boolean(35)) {
            return 'failed';
        }

        if ($score < 65) {
            return $faker->boolean(55) ? 'follow_up' : $preferredOutcome;
        }

        if ($score >= 85) {
            return 'success';
        }

        return $preferredOutcome;
    }

    private function callStatusForOutcome(string $outcome, Generator $faker): string
    {
        return match ($outcome) {
            'failed' => $faker->randomElement(['missed', 'failed', 'cancelled']),
            'escalated' => 'completed',
            default => 'completed',
        };
    }

    private function leadScoreForScenario(int $score, int $leadBias, Generator $faker): int
    {
        return max(15, min(98, $score + $leadBias + $faker->numberBetween(-10, 10)));
    }

    /** @param  array<string, mixed>  $scenario */
    private function buildTranscript(
        array $scenario,
        string $agentName,
        string $customerLabel,
        string $organizationTitle,
        string $direction,
        int $durationSeconds,
        Generator $faker,
    ): string {
        $opening = $direction === 'inbound'
            ? "کارشناس ({$agentName}): سلام، {$organizationTitle}، وقت بخیر. بفرمایید چطور می‌تونم کمکتون کنم؟"
            : "کارشناس ({$agentName}): سلام، وقت بخیر. از {$organizationTitle} تماس می‌گیرم. آیا با {$customerLabel} صحبت می‌کنم؟";

        $lines = [$opening];

        foreach ($scenario['dialogue'] as $turn) {
            $speaker = $turn['speaker'] === 'agent'
                ? "کارشناس ({$agentName})"
                : "مشتری ({$customerLabel})";
            $lines[] = "{$speaker}: {$turn['text']}";
        }

        $closing = match (true) {
            $durationSeconds < 180 => "کارشناس ({$agentName}): ممنون از تماس شما، روز خوبی داشته باشید.",
            default => "کارشناس ({$agentName}): جمع‌بندی کردیم و طبق توافق پیش می‌ریم. اگر سوال دیگری بود در خدمتیم. ممنون از وقت شما.",
        };

        $lines[] = $closing;

        if ($faker->boolean(40)) {
            $lines[] = "مشتری ({$customerLabel}): ممنون، خداحافظ.";
        }

        return implode("\n\n", $lines);
    }

    /** @param  array<string, mixed>  $scenario */
    private function buildSummary(
        array $scenario,
        string $agentName,
        string $customerLabel,
        string $companyLabel,
        string $organizationTitle,
        string $outcome,
        int $durationSeconds,
    ): string {
        $minutes = max(1, (int) round($durationSeconds / 60));

        $paragraphs = [
            "مشتری {$customerLabel} از {$companyLabel} با {$organizationTitle} تماس گرفت. {$scenario['summary_opening']} کارشناس {$agentName} با گوش دادن فعال و بررسی پرونده، وضعیت را برای مشتری شفاف کرد.",
            "در ادامه مکالمه، {$scenario['summary_middle']} مدت مکالمه حدود {$minutes} دقیقه بود و مشتری در پایان تماس ".DemoCatalog::callOutcomeLabel($outcome).' بود.',
            $scenario['summary_close'],
        ];

        return implode("\n\n", $paragraphs);
    }

    private function overallEvaluation(int $score, string $outcome): string
    {
        return match (true) {
            $outcome === 'failed' => 'تماس به نتیجه مطلوب نرسید؛ نیاز به بازنگری در زمان‌بندی تماس و آماده‌سازی پاسخ‌های اولیه وجود دارد.',
            $outcome === 'escalated' => 'کارشناس مسئله را مدیریت کرد اما برای جلوگیری از تشدید، پیگیری مدیریتی توصیه می‌شود.',
            $score >= 85 => 'عملکرد بسیار خوب؛ ارتباط حرفه‌ای، پاسخ‌گویی دقیق و جمع‌بندی مناسب در پایان تماس.',
            $score >= 70 => 'عملکرد قابل قبول با فرصت‌هایی برای تقویت جمع‌بندی و پیشنهاد فروش مکمل.',
            default => 'نیاز به بهبود در مدیریت اعتراض و تثبیت نیاز مشتری پیش از پایان تماس.',
        };
    }

    /** @return list<string> */
    private function nextActionsForOutcome(string $outcome, array $scenario, Generator $faker): array
    {
        $base = $scenario['next_actions'];

        return match ($outcome) {
            'failed' => ['تماس مجدد در اولین بازه کاری فردا', 'بررسی علت قطع یا عدم پاسخ مشتری'],
            'escalated' => array_merge($base, ['اطلاع‌رسانی به سرپرست تیم برای پیگیری ویژه']),
            'follow_up' => array_merge($base, ['ثبت یادآور پیگیری در سیستم']),
            default => $base,
        };
    }

    /** @return array<string, array{score: int}> */
    private function performanceDimensions(int $score, Generator $faker): array
    {
        $keys = [
            'communication_skills',
            'product_knowledge',
            'objection_handling',
            'closing_ability',
            'professionalism',
        ];

        $dimensions = [];

        foreach ($keys as $index => $key) {
            $delta = match ($key) {
                'closing_ability' => $faker->numberBetween(-12, 4),
                'objection_handling' => $faker->numberBetween(-8, 6),
                default => $faker->numberBetween(-6, 6),
            };
            $dimensions[$key] = ['score' => max(35, min(100, $score + $delta + ($index % 2)))];
        }

        return $dimensions;
    }
}
