<?php

namespace Tests\Feature;

use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Enums\UserRole;
use App\Livewire\Employer\Dashboard\Overview;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\EmployerDashboardAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SentimentCustomersDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_lists_satisfied_and_dissatisfied_customers_between_opportunities_and_weaknesses(): void
    {
        $organization = $this->actingAsEmployer();
        $this->seedConversation($organization, [
            'external_id' => 'happy-1',
            'customer_name' => 'نیما رضایی',
            'customer_phone' => '09125551111',
            'company' => 'شرکت سپهر',
            'sentiment' => AnalysisSentiment::Positive,
            'strength' => 'برخورد گرم و پیگیری دقیق',
            'summary' => 'مشتری از خدمات راضی بود.',
            'analyzed_at' => now()->subDay(),
        ]);
        $this->seedConversation($organization, [
            'external_id' => 'unhappy-1',
            'customer_name' => 'مریم کاظمی',
            'customer_phone' => '09126662222',
            'company' => 'شرکت آفتاب',
            'sentiment' => AnalysisSentiment::Negative,
            'concern' => 'تأخیر در ارسال سفارش',
            'weakness' => 'عدم همدلی با مشتری ناراضی',
            'summary' => 'مشتری از تأخیر ناراضی بود.',
            'analyzed_at' => now()->subHours(5),
        ]);

        $html = Livewire::test(Overview::class)
            ->assertSee('مشتریان راضی')
            ->assertSee('مشتریان ناراضی')
            ->assertSee('نیما رضایی')
            ->assertSee('شرکت سپهر')
            ->assertSee('09125551111')
            ->assertSee('برخورد گرم و پیگیری دقیق')
            ->assertSee('مریم کاظمی')
            ->assertSee('شرکت آفتاب')
            ->assertSee('09126662222')
            ->assertSee('تأخیر در ارسال سفارش')
            ->assertSee('فرصت‌های معاملاتی جدید')
            ->assertSee('ضعف‌های پرتکرار تیم')
            ->html();

        $opportunitiesPosition = mb_strpos($html, 'فرصت‌های معاملاتی جدید');
        $sentimentPosition = mb_strpos($html, 'data-tour="dashboard-sentiment-customers"');
        $weaknessesPosition = mb_strpos($html, 'ضعف‌های پرتکرار تیم');

        $this->assertNotFalse($opportunitiesPosition);
        $this->assertNotFalse($sentimentPosition);
        $this->assertNotFalse($weaknessesPosition);
        $this->assertLessThan($sentimentPosition, $opportunitiesPosition);
        $this->assertLessThan($weaknessesPosition, $sentimentPosition);
    }

    public function test_analytics_keeps_recent_positive_and_negative_conversations_and_dedupes_customers(): void
    {
        $organization = Organization::factory()->create();
        $other = Organization::factory()->create();

        $this->seedConversation($organization, [
            'external_id' => 'keep-happy-old',
            'customer_name' => 'راضی قدیمی',
            'customer_phone' => '09127770001',
            'sentiment' => AnalysisSentiment::Positive,
            'strength' => 'نقطه قوت قدیمی',
            'analyzed_at' => now()->subDays(3),
        ]);
        $this->seedConversation($organization, [
            'external_id' => 'keep-happy-latest',
            'customer_name' => 'راضی تازه',
            'customer_phone' => '09127770001',
            'sentiment' => AnalysisSentiment::Positive,
            'strength' => 'نقطه قوت تازه',
            'analyzed_at' => now()->subDay(),
        ]);
        $this->seedConversation($organization, [
            'external_id' => 'keep-unhappy',
            'customer_name' => 'ناراضی معتبر',
            'customer_phone' => '09127770002',
            'sentiment' => AnalysisSentiment::Negative,
            'concern' => 'شکایت از پشتیبانی',
            'analyzed_at' => now()->subDays(2),
        ]);
        $this->seedConversation($organization, [
            'external_id' => 'skip-neutral',
            'customer_name' => 'مشتری خنثی',
            'customer_phone' => '09127770003',
            'sentiment' => AnalysisSentiment::Neutral,
            'analyzed_at' => now()->subDay(),
        ]);
        $this->seedConversation($organization, [
            'external_id' => 'skip-old',
            'customer_name' => 'ناراضی قدیمی',
            'customer_phone' => '09127770004',
            'sentiment' => AnalysisSentiment::Negative,
            'analyzed_at' => now()->subDays(40),
        ]);
        $this->seedConversation($other, [
            'external_id' => 'skip-other',
            'customer_name' => 'راضی سازمان دیگر',
            'customer_phone' => '09127770005',
            'sentiment' => AnalysisSentiment::Positive,
            'analyzed_at' => now()->subDay(),
        ]);
        $this->seedConversation($organization, [
            'external_id' => 'skip-unevaluable',
            'customer_name' => 'تماس بی‌کیفیت',
            'customer_phone' => '09127770006',
            'sentiment' => AnalysisSentiment::Positive,
            'score' => 0,
            'is_evaluable' => false,
            'analyzed_at' => now()->subDay(),
        ]);

        $customers = EmployerDashboardAnalytics::forOrganization($organization->id)->sentimentCustomers();

        $this->assertCount(1, $customers['satisfied']);
        $this->assertSame('راضی تازه', $customers['satisfied'][0]['customer']);
        $this->assertSame('نقطه قوت تازه', $customers['satisfied'][0]['highlight']);
        $this->assertSame('09127770001', $customers['satisfied'][0]['phone']);

        $this->assertCount(1, $customers['dissatisfied']);
        $this->assertSame('ناراضی معتبر', $customers['dissatisfied'][0]['customer']);
        $this->assertSame('شکایت از پشتیبانی', $customers['dissatisfied'][0]['highlight']);
    }

    public function test_empty_states_are_shown_when_there_are_no_sentiment_conversations(): void
    {
        $this->actingAsEmployer();

        Livewire::test(Overview::class)
            ->assertSee('مشتریان راضی')
            ->assertSee('مشتریان ناراضی')
            ->assertSee('هنوز مشتری راضی‌ای نیست')
            ->assertSee('هنوز مشتری ناراضی‌ای نیست');
    }

    private function actingAsEmployer(): Organization
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $employer->id]);

        $this->actingAs($employer);

        return $organization;
    }

    /** @param  array<string, mixed>  $data */
    private function seedConversation(Organization $organization, array $data): void
    {
        $employee = OrganizationUser::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'first_name' => 'علی',
                'last_name' => 'احمدی',
            ],
            [
                'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
                'is_active' => true,
            ],
        );

        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'novatel',
            'external_call_id' => $data['external_id'],
            'direction' => 'inbound',
            'caller_number' => $data['customer_phone'] ?? '09120000000',
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'] ?? '09120000000',
            'title' => 'پیگیری سفارش',
            'category' => 'پشتیبانی',
            'receiver_number' => '02100000000',
            'status' => 'completed',
            'processing_status' => 'analyzed',
            'duration_seconds' => 180,
            'started_at' => $data['analyzed_at'],
        ]);

        ConversationAnalysis::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'call_id' => $call->id,
            'source' => ConversationSource::Voip,
            'llm_provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'score' => $data['score'] ?? 80,
            'is_evaluable' => $data['is_evaluable'] ?? true,
            'summary' => $data['summary'] ?? ('خلاصه '.$data['customer_name']),
            'sentiment' => $data['sentiment'],
            'strengths_json' => array_values(array_filter([$data['strength'] ?? null])),
            'weaknesses_json' => array_values(array_filter([$data['weakness'] ?? null])),
            'concerns_json' => isset($data['concern'])
                ? [['type' => 'support', 'text' => $data['concern'], 'severity' => 'high']]
                : [],
            'next_actions_json' => [],
            'customer_identity_json' => [
                'person_name' => $data['customer_name'],
                'company_name' => $data['company'] ?? null,
                'phone_number' => $data['customer_phone'] ?? '09120000000',
            ],
            'analyzed_at' => $data['analyzed_at'],
        ]);
    }
}
