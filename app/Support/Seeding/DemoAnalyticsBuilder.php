<?php

namespace App\Support\Seeding;

use App\Domain\Call\Enums\CallProcessingStatus;
use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Customer;
use App\Models\LlmModel;
use App\Models\Organization;
use App\Models\OrganizationActivity;
use App\Models\OrganizationUser;
use App\Models\PlatformAiSettings;
use Illuminate\Support\Arr;

class DemoAnalyticsBuilder
{
    public function __construct(
        private ?DemoConversationContentBuilder $contentBuilder = null,
    ) {}

    public function seedForOrganization(Organization $organization, int $orgIndex): void
    {
        $contentBuilder = $this->contentBuilder ?? new DemoConversationContentBuilder;
        $organizationDefinition = DemoCatalog::organizations()[$orgIndex - 1] ?? DemoCatalog::organizations()[0];
        $employees = OrganizationUser::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->get();

        if ($employees->isEmpty()) {
            return;
        }

        $defaultModel = LlmModel::query()->where('is_active', true)->where('is_default', true)->first()
            ?? LlmModel::query()->where('is_active', true)->first();

        $customers = $this->seedCustomers($organization, $orgIndex);

        for ($callIndex = 1; $callIndex <= DemoCatalog::CALLS_PER_ORGANIZATION; $callIndex++) {
            $employee = $employees->get(($callIndex - 1) % $employees->count());
            $customer = $customers->get(($callIndex - 1) % $customers->count());
            $faker = fake();
            $faker->seed($organization->id * 9_001 + $callIndex);
            $startedAt = $this->startedAtForDemoCall($organization->id, $callIndex, $faker);
            $duration = $faker->numberBetween(120, 900);
            $endedAt = $startedAt->copy()->addSeconds($duration);
            $direction = $faker->randomElement(['inbound', 'outbound']);
            $externalId = "demo-{$organization->id}-call-{$callIndex}";
            $caller = $direction === 'inbound' ? $customer->phone_number : '021'.$faker->numberBetween(10000000, 99999999);
            $receiver = $direction === 'inbound' ? '0'.$faker->numberBetween(100, 999) : $customer->phone_number;

            $score = $this->scoreForEmployee($employee->id, $callIndex, $faker);
            $sentiment = match (true) {
                $score >= 85 => AnalysisSentiment::Positive,
                $score >= 70 => AnalysisSentiment::Neutral,
                $score >= 55 => AnalysisSentiment::Mixed,
                default => AnalysisSentiment::Negative,
            };

            $content = $contentBuilder->build(
                seed: $organization->id * 9_001 + $callIndex,
                score: $score,
                sentiment: $sentiment,
                employee: $employee,
                customer: $customer,
                direction: $direction,
                durationSeconds: $duration,
                organizationTitle: $organizationDefinition['title'],
            );

            $call = Call::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'provider_code' => 'demo',
                    'external_call_id' => $externalId,
                ],
                [
                    'organization_user_id' => $employee->id,
                    'customer_id' => $customer->id,
                    'source' => ConversationSource::Imported,
                    'direction' => $direction,
                    'caller_number' => $caller,
                    'receiver_number' => $receiver,
                    'customer_name' => $customer->displayName(),
                    'status' => $content['call_status'],
                    'processing_status' => CallProcessingStatus::Analyzed,
                    'started_at' => $startedAt,
                    'ended_at' => $endedAt,
                    'duration_seconds' => $duration,
                    'title' => $content['call_title'],
                    'category' => $content['call_category'],
                    'notes' => $content['call_notes'],
                    'metadata' => $content['metadata'],
                    'conversation_date' => $startedAt,
                ],
            );

            $analyzedAt = $endedAt->copy()->addMinutes($faker->numberBetween(2, 15));
            $inputTokens = $faker->numberBetween(3_000, 8_000);
            $outputTokens = $faker->numberBetween(600, 1_800);
            $cost = $defaultModel
                ? $defaultModel->calculateCost($inputTokens, $outputTokens)
                : $faker->randomFloat(4, 0.05, 0.35);

            ConversationAnalysis::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'call_id' => $call->id,
                ],
                [
                    'organization_user_id' => $employee->id,
                    'source' => ConversationSource::Imported,
                    'llm_provider' => $defaultModel?->provider?->code ?? 'openai',
                    'model_name' => $defaultModel?->model_key ?? 'gpt-4o-mini',
                    'llm_model_id' => $defaultModel?->id,
                    'prompt_version' => 'v1',
                    'score' => $score,
                    'summary' => $content['summary'],
                    'transcript' => $content['transcript'],
                    'sentiment' => $sentiment,
                    'overall_evaluation' => $content['overall_evaluation'],
                    'strengths_json' => $content['strengths_json'],
                    'weaknesses_json' => $content['weaknesses_json'],
                    'next_actions_json' => $content['next_actions_json'],
                    'performance_dimensions_json' => $content['performance_dimensions_json'],
                    'customer_insights_json' => $content['customer_insights_json'],
                    'operational_insights_json' => $content['operational_insights_json'],
                    'lead_quality_json' => $content['lead_quality_json'],
                    'concerns_json' => $content['concerns_json'],
                    'customer_identity_json' => $content['customer_identity_json'],
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'total_tokens' => $inputTokens + $outputTokens,
                    'cost' => $cost,
                    'input_price_snapshot' => $defaultModel
                        ? PlatformAiSettings::convertFromUnits((float) $defaultModel->input_price_per_million_tokens)
                        : null,
                    'output_price_snapshot' => $defaultModel
                        ? PlatformAiSettings::convertFromUnits((float) $defaultModel->output_price_per_million_tokens)
                        : null,
                    'processing_duration_ms' => $faker->numberBetween(1_800, 4_500),
                    'analyzed_at' => $analyzedAt,
                ],
            );

        }

        $this->seedOrganizationActivities($organization);
        $this->syncCustomerStats($customers);
    }

    private function seedOrganizationActivities(Organization $organization): void
    {
        OrganizationActivity::query()
            ->where('organization_id', $organization->id)
            ->where('metadata->source', 'demo_seeder')
            ->delete();

        $items = ConversationAnalysis::query()
            ->where('organization_id', $organization->id)
            ->with(['call:id,customer_id,customer_name,caller_number', 'call.customer:id,name,company_name', 'employee:user_id,id'])
            ->latest('analyzed_at')
            ->limit(10)
            ->get();

        foreach ($items->take(6) as $analysis) {
            $call = $analysis->call;
            $customer = $call?->customer?->displayName()
                ?? $call?->customer_name
                ?? $call?->caller_number
                ?? 'مکالمه';

            OrganizationActivity::query()->create([
                'organization_id' => $organization->id,
                'user_id' => $analysis->employee?->user_id,
                'type' => 'analysis_completed',
                'title' => 'تحلیل تماس تکمیل شد',
                'description' => "مکالمه با {$customer} با امتیاز {$analysis->score} ثبت شد.",
                'metadata' => [
                    'source' => 'demo_seeder',
                    'analysis_id' => $analysis->id,
                    'call_id' => $analysis->call_id,
                ],
                'created_at' => $analysis->analyzed_at,
                'updated_at' => $analysis->analyzed_at,
            ]);
        }
    }

    /** @param  \Illuminate\Support\Collection<int, Customer>  $customers */
    private function syncCustomerStats(\Illuminate\Support\Collection $customers): void
    {
        foreach ($customers as $customer) {
            $calls = Call::query()
                ->where('customer_id', $customer->id)
                ->with('analyses:id,call_id,analyzed_at,lead_quality_json')
                ->get();

            $latestAnalysis = $calls
                ->flatMap(fn (Call $call) => $call->analyses)
                ->sortByDesc('analyzed_at')
                ->first();

            $customer->update([
                'total_calls' => $calls->count(),
                'total_answered_calls' => $calls->where('status', 'completed')->count(),
                'first_contact_at' => $calls->min('started_at'),
                'last_contact_at' => $calls->max('started_at'),
                'latest_lead_score' => $latestAnalysis?->lead_quality_json['score'] ?? $customer->latest_lead_score,
                'latest_lead_level' => $latestAnalysis?->lead_quality_json['level'] ?? $customer->latest_lead_level,
            ]);
        }
    }

    /** @return \Illuminate\Support\Collection<int, Customer> */
    private function seedCustomers(Organization $organization, int $orgIndex): \Illuminate\Support\Collection
    {
        $customers = collect();
        $names = DemoCatalog::customerNames();
        $faker = fake();
        $faker->seed($organization->id * 3_331);

        for ($i = 1; $i <= DemoCatalog::CUSTOMERS_PER_ORGANIZATION; $i++) {
            $phone = DemoCatalog::formatMobile($orgIndex, $i);
            $normalized = DemoCatalog::normalizePhone($phone);

            $customer = Customer::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'normalized_phone' => $normalized,
                ],
                [
                    'phone_number' => $phone,
                    'name' => $names[($i - 1) % count($names)].' #'.$i,
                    'company_name' => $names[($i - 1) % count($names)],
                    'email' => "customer-{$organization->id}-{$i}@example.com",
                    'job_title' => $faker->randomElement(['مدیر خرید', 'مسئول فنی', 'مالک کسب‌وکار', 'منشی']),
                    'identity_confidence' => $faker->randomFloat(2, 0.55, 0.98),
                    'purchase_intent' => $faker->randomElement(['بالا', 'متوسط', 'پایین']),
                    'conversation_trend' => $faker->randomElement(['improving', 'stable', 'declining']),
                    'recommended_next_action' => Arr::random(DemoCatalog::nextActions()),
                    'common_concerns_json' => [
                        ['type' => 'price', 'count' => $faker->numberBetween(1, 4)],
                        ['type' => 'timing', 'count' => $faker->numberBetween(0, 2)],
                    ],
                ],
            );

            $customers->push($customer);
        }

        return $customers;
    }

    private function startedAtForDemoCall(int $organizationId, int $callIndex, \Faker\Generator $faker): \Carbon\Carbon
    {
        if ($callIndex <= DemoCatalog::CALLS_TODAY_PER_ORGANIZATION) {
            return now()->startOfDay()
                ->addHours($faker->numberBetween(8, 18))
                ->addMinutes($faker->numberBetween(0, 59))
                ->addSeconds($faker->numberBetween(0, 59));
        }

        $pastDaySpan = max(1, DemoCatalog::DEMO_CALL_RECENT_DAYS - 1);
        $daysAgo = 1 + (($callIndex - DemoCatalog::CALLS_TODAY_PER_ORGANIZATION - 1) % $pastDaySpan);

        return now()->subDays($daysAgo)->startOfDay()
            ->addHours($faker->numberBetween(8, 18))
            ->addMinutes($faker->numberBetween(0, 59))
            ->addSeconds($faker->numberBetween(0, 59));
    }

    private function scoreForEmployee(int $employeeId, int $callIndex, \Faker\Generator $faker): int
    {
        $base = 52 + ($employeeId % 7) * 5;
        $variance = ($callIndex % 5) * 3 + $faker->numberBetween(-6, 8);

        return max(45, min(98, $base + $variance));
    }
}
