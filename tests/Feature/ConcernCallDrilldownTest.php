<?php

namespace Tests\Feature;

use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\DTOs\AnalysisListFilter;
use App\Enums\ReportDatePreset;
use App\Enums\UserRole;
use App\Livewire\Employer\Intelligence\Index;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\AnalysisListQuery;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ConcernCallDrilldownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-16 12:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_clicking_a_concern_lists_only_matching_calls(): void
    {
        $organization = $this->actingAsEmployer();
        $employee = $this->employee($organization, 'سارا', 'احمدی');

        $this->seedCall($organization, $employee, 'price-call', 'مشتری قیمت', [
            ['type' => 'price', 'text' => 'نگرانی از هزینه تمدید سالانه', 'severity' => 'high'],
            ['type' => 'price', 'text' => 'درخواست تخفیف بیشتر روی پلن', 'severity' => 'medium'],
        ], Carbon::parse('2026-09-15 10:00:00', 'Asia/Tehran'));

        $this->seedCall($organization, $employee, 'trust-call', 'مشتری اعتماد', [
            ['type' => 'trust', 'text' => 'تردید درباره سابقه شرکت', 'severity' => 'medium'],
        ], Carbon::parse('2026-09-15 11:00:00', 'Asia/Tehran'));

        Livewire::test(Index::class)
            ->assertSee('برای دیدن تماس‌ها روی هر مورد کلیک کنید.')
            ->assertSee('data-drilldown="concern"', false)
            ->assertSee('wire:ignore', false)
            ->assertDontSee('نگرانی از هزینه تمدید سالانه')
            ->call('drilldown', 'concern', 'price')
            ->assertSet('selectedConcern', 'price')
            ->assertSee('تماس‌های مرتبط با')
            ->assertSee('id="concern-call-list"', false)
            ->assertSee('قیمت')
            ->assertSee('مشتری قیمت')
            ->assertSee('نگرانی از هزینه تمدید سالانه')
            ->assertSee('درخواست تخفیف بیشتر روی پلن')
            ->assertSee('شدت بالا')
            ->assertDontSee('تردید درباره سابقه شرکت')
            ->call('drilldown', 'concern', 'trust')
            ->assertSet('selectedConcern', 'trust')
            ->assertSee('تردید درباره سابقه شرکت')
            ->assertDontSee('نگرانی از هزینه تمدید سالانه')
            ->call('drilldown', 'concern', 'trust')
            ->assertSet('selectedConcern', null)
            ->assertDontSee('تردید درباره سابقه شرکت')
            ->call('selectConcern', 'price')
            ->call('clearConcern')
            ->assertSet('selectedConcern', null)
            ->assertDontSee('نگرانی از هزینه تمدید سالانه');
    }

    public function test_drilldown_respects_the_date_filter(): void
    {
        $organization = $this->actingAsEmployer();
        $employee = $this->employee($organization, 'سارا', 'احمدی');

        $this->seedCall($organization, $employee, 'recent-price', 'مشتری جدید', [
            ['type' => 'price', 'text' => 'نگرانی قیمت در تماس جدید', 'severity' => 'low'],
        ], Carbon::parse('2026-09-15 10:00:00', 'Asia/Tehran'));

        $this->seedCall($organization, $employee, 'old-price', 'مشتری قدیمی', [
            ['type' => 'price', 'text' => 'نگرانی قیمت در تماس قدیمی', 'severity' => 'high'],
        ], Carbon::parse('2026-08-05 10:00:00', 'Asia/Tehran'));

        Livewire::test(Index::class)
            ->call('selectConcern', 'price')
            ->assertSee('نگرانی قیمت در تماس جدید')
            ->assertDontSee('نگرانی قیمت در تماس قدیمی')
            ->call('applyCustomDateRange', '2026-08-01', '2026-08-10')
            ->assertSet('selectedConcern', 'price')
            ->assertSee('نگرانی قیمت در تماس قدیمی')
            ->assertDontSee('نگرانی قیمت در تماس جدید');
    }

    public function test_red_sentiment_slice_lists_only_negative_calls(): void
    {
        $organization = $this->actingAsEmployer();
        $employee = $this->employee($organization, 'سارا', 'احمدی');
        $at = Carbon::parse('2026-09-15 10:00:00', 'Asia/Tehran');

        $this->seedCall(
            $organization,
            $employee,
            'negative-call',
            'مشتری ناراضی',
            [],
            $at,
            AnalysisSentiment::Negative,
            'نارضایتی شدید از برخورد کارشناس',
        );
        $this->seedCall(
            $organization,
            $employee,
            'positive-call',
            'مشتری راضی',
            [],
            $at,
            AnalysisSentiment::Positive,
            'رضایت کامل از پاسخگویی',
        );

        $other = Organization::factory()->create();
        $this->seedCall(
            $other,
            $this->employee($other, 'علی', 'دیگر'),
            'foreign-negative',
            'مشتری سازمان دیگر',
            [],
            $at,
            AnalysisSentiment::Negative,
            'نارضایتی سازمان دیگر',
        );

        $component = Livewire::test(Index::class)
            ->assertSee('برای دیدن تماس‌ها، بخش قرمز را انتخاب کنید')
            ->assertSee('data-drilldown-allow="negative"', false)
            ->assertDontSee('تماس‌های با احساس منفی')
            ->call('selectNegativeSentiment', 'positive')
            ->assertSet('selectedSentiment', null)
            ->call('selectNegativeSentiment', 'negative')
            ->assertSet('selectedSentiment', 'negative')
            ->assertSee('تماس‌های با احساس منفی');

        $list = $this->sentimentListHtml($component->html());

        $this->assertStringContainsString('مشتری ناراضی', $list);
        $this->assertStringContainsString('نارضایتی شدید از برخورد کارشناس', $list);
        $this->assertStringNotContainsString('مشتری راضی', $list);
        $this->assertStringNotContainsString('نارضایتی سازمان دیگر', $list);

        $component->call('clearSentiment')
            ->assertSet('selectedSentiment', null)
            ->assertDontSee('id="sentiment-call-list"', false);

        $old = Carbon::parse('2026-08-05 10:00:00', 'Asia/Tehran');
        $this->seedCall(
            $organization,
            $employee,
            'old-negative',
            'مشتری قدیمی منفی',
            [],
            $old,
            AnalysisSentiment::Negative,
            'نارضایتی تماس قدیمی',
        );

        Livewire::test(Index::class)
            ->call('selectNegativeSentiment', 'negative')
            ->assertSee('نارضایتی شدید از برخورد کارشناس')
            ->assertDontSee('نارضایتی تماس قدیمی')
            ->call('applyCustomDateRange', '2026-08-01', '2026-08-10')
            ->assertSet('selectedSentiment', 'negative')
            ->assertSee('نارضایتی تماس قدیمی')
            ->assertDontSee('نارضایتی شدید از برخورد کارشناس');
    }

    public function test_drilldown_rejects_unknown_concerns_and_other_organizations(): void
    {
        $organization = $this->actingAsEmployer();
        $employee = $this->employee($organization, 'سارا', 'احمدی');
        $this->seedCall($organization, $employee, 'own-price', 'مشتری خودی', [
            ['type' => 'price', 'text' => 'نگرانی قیمت سازمان خودی', 'severity' => 'medium'],
        ], Carbon::parse('2026-09-15 10:00:00', 'Asia/Tehran'));

        $other = Organization::factory()->create();
        $otherEmployee = $this->employee($other, 'علی', 'دیگر');
        $this->seedCall($other, $otherEmployee, 'foreign-price', 'مشتری سازمان دیگر', [
            ['type' => 'price', 'text' => 'نگرانی قیمت سازمان دیگر', 'severity' => 'high'],
        ], Carbon::parse('2026-09-15 10:00:00', 'Asia/Tehran'));

        $filter = AnalysisListFilter::make($organization->id, ReportDatePreset::Last30);
        $result = app(AnalysisListQuery::class)->callsForConcern($filter, 'price');

        $this->assertSame(1, $result['total']);
        $this->assertSame('مشتری خودی', $result['calls'][0]['customer']);
        $this->assertSame('نگرانی قیمت سازمان خودی', $result['calls'][0]['concerns'][0]['text']);
        $this->assertSame(['total' => 0, 'calls' => []], app(AnalysisListQuery::class)->callsForConcern($filter, 'نگرانی ساختگی'));

        Livewire::test(Index::class)
            ->call('selectConcern', 'نگرانی ساختگی')
            ->assertSet('selectedConcern', null)
            ->assertDontSee('نگرانی قیمت سازمان دیگر')
            ->assertDontSee('تماسی با این نگرانی پیدا نشد.');
    }

    private function actingAsEmployer(): Organization
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $employer->id]);

        $this->actingAs($employer);

        return $organization;
    }

    private function employee(Organization $organization, string $firstName, string $lastName): OrganizationUser
    {
        return OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'is_active' => true,
        ]);
    }

    private function sentimentListHtml(string $html): string
    {
        $start = strpos($html, 'id="sentiment-call-list"');
        $end = strpos($html, 'id="analysis-list"', $start ?: 0);

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        return substr($html, $start, $end - $start);
    }

    /**
     * @param  list<array{type: string, text: string, severity: string}>  $concerns
     */
    private function seedCall(
        Organization $organization,
        OrganizationUser $employee,
        string $externalId,
        string $customerName,
        array $concerns,
        Carbon $at,
        AnalysisSentiment $sentiment = AnalysisSentiment::Neutral,
        ?string $summary = null,
    ): void {
        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'custom',
            'external_call_id' => $externalId,
            'direction' => 'inbound',
            'caller_number' => '09120000000',
            'receiver_number' => '101',
            'customer_name' => $customerName,
            'status' => 'completed',
            'processing_status' => 'analyzed',
            'duration_seconds' => 125,
            'started_at' => $at,
        ]);

        ConversationAnalysis::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'call_id' => $call->id,
            'source' => ConversationSource::Voip,
            'llm_provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'score' => 82,
            'is_evaluable' => true,
            'summary' => $summary ?? ('خلاصه '.$customerName),
            'sentiment' => $sentiment,
            'strengths_json' => [],
            'weaknesses_json' => [],
            'next_actions_json' => [],
            'concerns_json' => $concerns,
            'analyzed_at' => $at,
        ]);
    }
}
