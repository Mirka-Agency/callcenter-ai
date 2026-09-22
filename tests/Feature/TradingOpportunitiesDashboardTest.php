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
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class TradingOpportunitiesDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_lists_recent_high_quality_leads_above_team_weaknesses(): void
    {
        $organization = $this->actingAsEmployer();
        $this->seedOpportunity($organization, [
            'external_id' => 'opp-high',
            'customer_name' => 'رضا محمدی',
            'customer_phone' => '09121234567',
            'company' => 'شرکت پارس',
            'lead_level' => 'high',
            'lead_score' => 88,
            'lead_reason' => 'مشتری آماده خرید اشتراک سالانه است.',
            'intent' => 'تمدید اشتراک سازمانی',
            'next_actions' => ['ارسال پیش‌فاکتور امروز'],
            'buying_signals' => ['درخواست پیش‌فاکتور'],
            'weakness' => 'جمع‌بندی ضعیف انتهای تماس',
            'analyzed_at' => now()->subDay(),
        ]);
        $this->seedOpportunity($organization, [
            'external_id' => 'opp-medium',
            'customer_name' => 'لید متوسط',
            'lead_level' => 'medium',
            'lead_score' => 55,
            'sentiment' => AnalysisSentiment::Neutral,
            'analyzed_at' => now()->subDay(),
        ]);

        $html = Livewire::test(Overview::class)
            ->assertSee('فرصت‌های معاملاتی جدید')
            ->assertSee('نام شخص/شرکت')
            ->assertSee('شماره تماس')
            ->assertSee('نام کارشناس')
            ->assertSee('تاریخ تماس')
            ->assertSee('محصول/سرویس قابل فروش')
            ->assertSee('کیفیت لید')
            ->assertSee('احتمال خرید')
            ->assertSee('رضا محمدی')
            ->assertSee('شرکت پارس')
            ->assertSee('09121234567')
            ->assertSee('علی احمدی')
            ->assertSee('اشتراک سازمانی')
            ->assertDontSee('بالا')
            ->assertSee('saas-lead-quality')
            ->assertSee('88٪')
            ->assertSee('ارسال پیش‌فاکتور امروز')
            ->assertSee('درخواست پیش‌فاکتور')
            ->assertSee('پیگیری')
            ->assertSee('تگ‌های پیگیری')
            ->assertSee('تحلیل بیشتر')
            ->assertSee('ضعف‌های پرتکرار تیم')
            ->assertDontSee('لید متوسط')
            ->html();

        $opportunitiesPosition = mb_strpos($html, 'فرصت‌های معاملاتی جدید');
        $weaknessesPosition = mb_strpos($html, 'ضعف‌های پرتکرار تیم');

        $this->assertNotFalse($opportunitiesPosition);
        $this->assertNotFalse($weaknessesPosition);
        $this->assertLessThan($weaknessesPosition, $opportunitiesPosition);
    }

    public function test_analytics_excludes_low_quality_old_and_foreign_leads(): void
    {
        $organization = Organization::factory()->create();
        $other = Organization::factory()->create();

        $this->seedOpportunity($organization, [
            'external_id' => 'keep-high',
            'customer_name' => 'فرصت معتبر',
            'lead_level' => 'high',
            'lead_score' => 91,
            'analyzed_at' => now()->subDays(2),
        ]);
        $this->seedOpportunity($organization, [
            'external_id' => 'skip-old',
            'customer_name' => 'فرصت قدیمی',
            'lead_level' => 'high',
            'lead_score' => 90,
            'analyzed_at' => now()->subDays(40),
        ]);
        $this->seedOpportunity($organization, [
            'external_id' => 'skip-low',
            'customer_name' => 'فرصت ضعیف',
            'lead_level' => 'low',
            'lead_score' => 20,
            'analyzed_at' => now()->subDay(),
        ]);
        $this->seedOpportunity($other, [
            'external_id' => 'skip-other',
            'customer_name' => 'فرصت سازمان دیگر',
            'lead_level' => 'high',
            'lead_score' => 95,
            'analyzed_at' => now()->subDay(),
        ]);

        $opportunities = EmployerDashboardAnalytics::forOrganization($organization->id)->tradingOpportunities();

        $this->assertCount(1, $opportunities);
        $this->assertSame('فرصت معتبر', $opportunities[0]['customer']);
        $this->assertSame(91, $opportunities[0]['lead_score']);
        $this->assertSame('high', $opportunities[0]['lead_level']);
        $this->assertSame('اشتراک سازمانی', $opportunities[0]['product']);
        $this->assertArrayHasKey('sort_date', $opportunities[0]);
    }

    public function test_dashboard_sorts_trading_opportunities_by_clicked_column_titles(): void
    {
        $organization = $this->actingAsEmployer();
        $this->seedOpportunity($organization, [
            'external_id' => 'opp-newer-high-lead',
            'customer_name' => 'فرصت جدیدتر',
            'lead_level' => 'high',
            'lead_score' => 96,
            'purchase_probability' => 40,
            'analyzed_at' => now()->subDay(),
        ]);
        $this->seedOpportunity($organization, [
            'external_id' => 'opp-older-high-prob',
            'customer_name' => 'فرصت قدیمی‌تر',
            'lead_level' => 'high',
            'lead_score' => 72,
            'purchase_probability' => 90,
            'analyzed_at' => now()->subDays(5),
        ]);

        $component = Livewire::test(Overview::class);
        $html = $component->html();

        $this->assertSame(['فرصت جدیدتر', 'فرصت قدیمی‌تر'], $this->opportunityNames($component));
        $this->assertStringContainsString("sortBy('date')", $html);
        $this->assertStringContainsString("sortBy('lead_quality')", $html);
        $this->assertStringContainsString("sortBy('purchase_probability')", $html);
        $this->assertStringContainsString('saas-sort-icon', $html);
        $this->assertStringContainsString('data-sort-lead-quality="96"', $html);
        $this->assertStringContainsString('data-sort-lead-quality="72"', $html);
        $this->assertStringContainsString('data-sort-purchase-probability="40"', $html);
        $this->assertStringContainsString('data-sort-purchase-probability="90"', $html);
        $this->assertStringNotContainsString('wire:click="sortOpportunitiesBy', $html);
        $this->assertFalse(method_exists(Overview::class, 'sortOpportunitiesBy'));
    }

    public function test_empty_state_is_shown_when_there_are_no_high_quality_leads(): void
    {
        $this->actingAsEmployer();

        Livewire::test(Overview::class)
            ->assertSee('فرصت‌های معاملاتی جدید')
            ->assertSee('هنوز فرصت معاملاتی جدیدی نیست')
            ->assertSee('وقتی لید باکیفیتی اخیراً تماس بگیرد، برای پیگیری فروش اینجا دیده می‌شود.');
    }

    public function test_insight_lists_cutoff_hides_historical_and_demo_seed_leads(): void
    {
        config(['dashboard.insight_lists_since' => now()->subHour()->toIso8601String()]);

        $organization = Organization::factory()->create();

        $this->seedOpportunity($organization, [
            'external_id' => 'keep-fresh',
            'customer_name' => 'فرصت تازه',
            'lead_level' => 'high',
            'lead_score' => 92,
            'analyzed_at' => now()->subMinutes(10),
        ]);
        $this->seedOpportunity($organization, [
            'external_id' => 'skip-old',
            'customer_name' => 'فرصت قبل از ریست',
            'lead_level' => 'high',
            'lead_score' => 95,
            'analyzed_at' => now()->subHours(3),
        ]);
        $this->seedOpportunity($organization, [
            'external_id' => 'skip-demo',
            'customer_name' => 'فرصت دمو',
            'provider_code' => 'demo',
            'lead_level' => 'high',
            'lead_score' => 99,
            'analyzed_at' => now()->subMinutes(5),
        ]);

        $opportunities = EmployerDashboardAnalytics::forOrganization($organization->id)->tradingOpportunities();

        $this->assertCount(1, $opportunities);
        $this->assertSame('فرصت تازه', $opportunities[0]['customer']);
    }

    /**
     * @return list<string>
     */
    private function opportunityNames(Testable $component): array
    {
        return collect($component->viewData('tradingOpportunities'))->pluck('customer')->all();
    }

    private function actingAsEmployer(): Organization
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $employer->id]);

        $this->actingAs($employer);

        return $organization;
    }

    /** @param  array<string, mixed>  $data */
    private function seedOpportunity(Organization $organization, array $data): void
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
            'provider_code' => $data['provider_code'] ?? 'novatel',
            'external_call_id' => $data['external_id'],
            'direction' => 'inbound',
            'caller_number' => $data['customer_phone'] ?? '09120000000',
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'] ?? '09120000000',
            'title' => $data['product'] ?? $data['intent'] ?? 'تمدید اشتراک سازمانی',
            'category' => $data['category'] ?? 'فروش',
            'tags' => $data['buying_signals'] ?? [],
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
            'score' => 80,
            'is_evaluable' => true,
            'summary' => 'خلاصه '.$data['customer_name'],
            'sentiment' => $data['sentiment'] ?? AnalysisSentiment::Positive,
            'strengths_json' => [],
            'weaknesses_json' => array_filter([$data['weakness'] ?? null]),
            'next_actions_json' => $data['next_actions'] ?? ['تماس پیگیری فردا'],
            'lead_quality_json' => [
                'score' => $data['lead_score'],
                'level' => $data['lead_level'],
                'reason' => $data['lead_reason'] ?? 'دلیل کیفیت لید',
                'buying_intent_signals' => $data['buying_signals'] ?? [],
            ],
            'customer_insights_json' => [
                'intent' => $data['intent'] ?? 'استعلام قیمت',
                'purchase_probability' => $data['purchase_probability'] ?? $data['lead_score'],
            ],
            'operational_insights_json' => [
                'important_keywords' => $data['keywords'] ?? $data['buying_signals'] ?? [],
                'follow_up_suggestions' => $data['next_actions'] ?? [],
            ],
            'customer_identity_json' => [
                'person_name' => $data['customer_name'],
                'company_name' => $data['company'] ?? null,
                'phone_number' => $data['customer_phone'] ?? '09120000000',
            ],
            'analyzed_at' => $data['analyzed_at'],
        ]);
    }
}
