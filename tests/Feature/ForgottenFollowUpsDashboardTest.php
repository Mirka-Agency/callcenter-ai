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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class ForgottenFollowUpsDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-17 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_lists_overdue_ai_follow_ups_below_agent_performance(): void
    {
        $organization = $this->actingAsEmployer();
        $this->seedFollowUp($organization, [
            'external_id' => 'forgotten-overdue',
            'customer_name' => 'سارا کریمی',
            'customer_phone' => '09123334455',
            'company' => 'شرکت آریا',
            'follow_up' => 'تماس پیگیری فردا برای ارسال قرارداد',
            'analyzed_at' => now()->subDays(4),
            'started_at' => now()->subDays(4),
        ]);

        $html = Livewire::test(Overview::class)
            ->assertSee('پیگیری‌های فراموش‌شده')
            ->assertSee('نام شرکت یا مشتری')
            ->assertSee('شماره تماس')
            ->assertSee('نام کارشناس')
            ->assertSee('اقدام فراموش‌شده')
            ->assertSee('تاریخ پیگیری')
            ->assertSee('سارا کریمی')
            ->assertSee('شرکت آریا')
            ->assertSee('09123334455')
            ->assertSee('علی احمدی')
            ->assertSee('تماس پیگیری فردا برای ارسال قرارداد')
            ->assertSee('روز تأخیر')
            ->assertSee('اقدام‌های موعدگذشته')
            ->assertSee('نمایش تحلیل')
            ->assertSee('عملکرد کارشناسان')
            ->html();

        $agentsPosition = mb_strpos($html, 'data-tour="dashboard-agents"');
        $forgottenPosition = mb_strpos($html, 'data-tour="dashboard-forgotten-followups"');

        $this->assertNotFalse($agentsPosition);
        $this->assertNotFalse($forgottenPosition);
        $this->assertLessThan($forgottenPosition, $agentsPosition);
    }

    public function test_analytics_keeps_only_overdue_unfollowed_ai_follow_ups(): void
    {
        $organization = Organization::factory()->create();
        $other = Organization::factory()->create();

        $this->seedFollowUp($organization, [
            'external_id' => 'keep-overdue',
            'customer_name' => 'پیگیری معوق',
            'customer_phone' => '09120000001',
            'follow_up' => 'تماس پیگیری فردا',
            'analyzed_at' => now()->subDays(5),
            'started_at' => now()->subDays(5),
        ]);
        $this->seedFollowUp($organization, [
            'external_id' => 'skip-future',
            'customer_name' => 'پیگیری آینده',
            'customer_phone' => '09120000002',
            'follow_up' => 'پیگیری هفته آینده',
            'analyzed_at' => now()->subDay(),
            'started_at' => now()->subDay(),
        ]);
        $this->seedFollowUp($organization, [
            'external_id' => 'skip-done',
            'customer_name' => 'پیگیری انجام‌شده',
            'customer_phone' => '09120000003',
            'follow_up' => 'تماس پیگیری فردا',
            'analyzed_at' => now()->subDays(6),
            'started_at' => now()->subDays(6),
            'followed_up_at' => now()->subDays(2),
        ]);
        $this->seedFollowUp($organization, [
            'external_id' => 'keep-inbound-later',
            'customer_name' => 'تماس ورودی بعدی',
            'customer_phone' => '09120000005',
            'follow_up' => 'تماس پیگیری فردا',
            'analyzed_at' => now()->subDays(5),
            'started_at' => now()->subDays(5),
            'followed_up_at' => now()->subDays(2),
            'follow_up_direction' => 'inbound',
        ]);
        $this->seedFollowUp($other, [
            'external_id' => 'skip-other',
            'customer_name' => 'پیگیری سازمان دیگر',
            'customer_phone' => '09120000004',
            'follow_up' => 'تماس پیگیری فردا',
            'analyzed_at' => now()->subDays(5),
            'started_at' => now()->subDays(5),
        ]);

        $forgotten = EmployerDashboardAnalytics::forOrganization($organization->id)->forgottenFollowUps();

        $this->assertCount(2, $forgotten);
        $this->assertEqualsCanonicalizing(
            ['پیگیری معوق', 'تماس ورودی بعدی'],
            array_column($forgotten, 'customer'),
        );
        $this->assertSame('تماس پیگیری فردا', $forgotten[0]['forgotten_action']);
        $this->assertGreaterThan(0, $forgotten[0]['days_overdue']);
        $this->assertArrayHasKey('sort_due_date', $forgotten[0]);
    }

    public function test_forgotten_follow_up_lookup_ignores_unrelated_outbound_volume(): void
    {
        $organization = Organization::factory()->create();

        $this->seedFollowUp($organization, [
            'external_id' => 'keep-overdue-volume',
            'customer_name' => 'پیگیری معوق',
            'customer_phone' => '09120000001',
            'follow_up' => 'تماس پیگیری فردا',
            'analyzed_at' => now()->subDays(5),
            'started_at' => now()->subDays(5),
        ]);

        $employee = OrganizationUser::query()->where('organization_id', $organization->id)->firstOrFail();

        for ($i = 0; $i < 80; $i++) {
            Call::query()->create([
                'organization_id' => $organization->id,
                'organization_user_id' => $employee->id,
                'source' => ConversationSource::Voip,
                'provider_code' => 'novatel',
                'external_call_id' => 'unrelated-out-'.$i,
                'direction' => 'outbound',
                'caller_number' => '02100000000',
                'customer_name' => 'مشتری نامرتبط '.$i,
                'customer_phone' => '0912999'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'receiver_number' => '0912999'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'title' => 'تماس نامرتبط',
                'category' => 'فروش',
                'status' => 'completed',
                'processing_status' => 'analyzed',
                'duration_seconds' => 60,
                'started_at' => now()->subDays(2),
            ]);
        }

        $forgotten = EmployerDashboardAnalytics::forOrganization($organization->id)->forgottenFollowUps();

        $this->assertCount(1, $forgotten);
        $this->assertSame('پیگیری معوق', $forgotten[0]['customer']);
    }

    public function test_dashboard_sorts_forgotten_follow_ups_by_due_date_title(): void
    {
        $organization = $this->actingAsEmployer();
        $this->seedFollowUp($organization, [
            'external_id' => 'forgotten-older',
            'customer_name' => 'پیگیری قدیمی‌تر',
            'customer_phone' => '09121110001',
            'follow_up' => 'تماس پیگیری فردا',
            'analyzed_at' => now()->subDays(8),
            'started_at' => now()->subDays(8),
        ]);
        $this->seedFollowUp($organization, [
            'external_id' => 'forgotten-newer',
            'customer_name' => 'پیگیری جدیدتر',
            'customer_phone' => '09121110002',
            'follow_up' => 'تماس پیگیری فردا',
            'analyzed_at' => now()->subDays(3),
            'started_at' => now()->subDays(3),
        ]);

        $component = Livewire::test(Overview::class);
        $html = $component->html();

        $this->assertSame(['پیگیری قدیمی‌تر', 'پیگیری جدیدتر'], $this->forgottenNames($component));
        $this->assertStringContainsString("sortBy('due_date')", $html);
        $this->assertStringContainsString('saas-sort-icon', $html);
        $this->assertStringContainsString('data-sort-due-date="', $html);
        $this->assertStringNotContainsString('wire:click="sortForgottenBy', $html);
        $this->assertFalse(method_exists(Overview::class, 'sortForgottenBy'));
    }

    public function test_empty_state_is_shown_when_there_are_no_forgotten_follow_ups(): void
    {
        $this->actingAsEmployer();

        Livewire::test(Overview::class)
            ->assertSee('پیگیری‌های فراموش‌شده')
            ->assertSee('پیگیری فراموش‌شده‌ای نیست')
            ->assertSee('اگر موعد پیگیری پیشنهادی هوش مصنوعی بگذرد و کارشناس تماس نگیرد، اینجا دیده می‌شود.');
    }

    /**
     * @return list<string>
     */
    private function forgottenNames(Testable $component): array
    {
        return collect($component->viewData('forgottenFollowUps'))->pluck('customer')->all();
    }

    private function actingAsEmployer(): Organization
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $employer->id]);

        $this->actingAs($employer);

        return $organization;
    }

    /** @param  array<string, mixed>  $data */
    private function seedFollowUp(Organization $organization, array $data): void
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
            'title' => 'پیگیری قرارداد',
            'category' => 'فروش',
            'receiver_number' => '02100000000',
            'status' => 'completed',
            'processing_status' => 'analyzed',
            'duration_seconds' => 180,
            'started_at' => $data['started_at'] ?? $data['analyzed_at'],
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
            'sentiment' => AnalysisSentiment::Positive,
            'strengths_json' => [],
            'weaknesses_json' => [],
            'next_actions_json' => [$data['follow_up']],
            'operational_insights_json' => [
                'follow_up_suggestions' => [$data['follow_up']],
            ],
            'customer_identity_json' => [
                'person_name' => $data['customer_name'],
                'company_name' => $data['company'] ?? null,
                'phone_number' => $data['customer_phone'] ?? '09120000000',
            ],
            'analyzed_at' => $data['analyzed_at'],
        ]);

        if (! empty($data['followed_up_at'])) {
            Call::query()->create([
                'organization_id' => $organization->id,
                'organization_user_id' => $employee->id,
                'source' => ConversationSource::Voip,
                'provider_code' => 'novatel',
                'external_call_id' => $data['external_id'].'-followup',
                'direction' => $data['follow_up_direction'] ?? 'outbound',
                'caller_number' => ($data['follow_up_direction'] ?? 'outbound') === 'inbound'
                    ? ($data['customer_phone'] ?? '09120000000')
                    : '02100000000',
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'] ?? '09120000000',
                'receiver_number' => ($data['follow_up_direction'] ?? 'outbound') === 'inbound'
                    ? '02100000000'
                    : ($data['customer_phone'] ?? '09120000000'),
                'title' => 'تماس پیگیری',
                'category' => 'فروش',
                'status' => 'completed',
                'processing_status' => 'analyzed',
                'duration_seconds' => 90,
                'started_at' => $data['followed_up_at'],
            ]);
        }
    }
}
