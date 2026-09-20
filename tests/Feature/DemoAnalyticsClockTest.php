<?php

namespace Tests\Feature;

use App\Domain\Call\Enums\CallProcessingStatus;
use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Livewire\Employer\Dashboard\Overview;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\Demo\DemoAnalyticsClock;
use App\Services\Reports\OrganizationCallMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DemoAnalyticsClockTest extends TestCase
{
    use RefreshDatabase;

    public function test_shifts_stale_demo_calls_onto_today_so_dashboard_count_is_nonzero(): void
    {
        $organization = $this->demoOrganization();
        $staleToday = now()->subDays(3)->setTime(10, 15);
        $staleYesterday = now()->subDays(4)->setTime(11, 0);

        $this->seedDemoCall($organization, 1, $staleToday, 88);
        $this->seedDemoCall($organization, 2, $staleYesterday, 70);

        $this->assertSame(0, app(OrganizationCallMetrics::class)->countToday($organization->id));

        $shifted = app(DemoAnalyticsClock::class)->refreshIfStale($organization);

        $this->assertSame(3, $shifted);
        $this->assertSame(1, app(OrganizationCallMetrics::class)->countToday($organization->id));
        $this->assertSame(
            now()->toDateString(),
            Call::query()->where('external_call_id', "demo-{$organization->id}-call-1")->value('started_at')?->toDateString(),
        );
        $this->assertSame(
            now()->toDateString(),
            ConversationAnalysis::query()->where('organization_id', $organization->id)->orderByDesc('analyzed_at')->value('analyzed_at')?->toDateString(),
        );
    }

    public function test_does_not_shift_production_organizations_or_real_calls_on_demo_orgs(): void
    {
        $production = Organization::factory()->create(['is_demo' => false]);
        $this->seedDemoCall($production, 1, now()->subDays(2)->setTime(9, 0), 80);

        $demo = $this->demoOrganization();
        $this->seedDemoCall($demo, 1, now()->subDays(2)->setTime(10, 0), 81);
        $real = Call::query()->create([
            'organization_id' => $demo->id,
            'external_call_id' => 'uploaded-recording',
            'provider_code' => 'manual',
            'source' => ConversationSource::ManualUpload,
            'direction' => 'inbound',
            'caller_number' => '09120000001',
            'receiver_number' => '02100000001',
            'status' => 'completed',
            'processing_status' => CallProcessingStatus::Analyzed,
            'started_at' => now()->subDays(2)->setTime(16, 0),
        ]);

        $clock = app(DemoAnalyticsClock::class);

        $this->assertSame(0, $clock->refreshIfStale($production));
        $this->assertSame(
            now()->subDays(2)->toDateString(),
            Call::query()->where('organization_id', $production->id)->value('started_at')?->toDateString(),
        );

        $clock->refreshIfStale($demo->fresh());

        $this->assertSame(now()->toDateString(), Call::query()->where('external_call_id', "demo-{$demo->id}-call-1")->value('started_at')?->toDateString());
        $this->assertSame(
            now()->subDays(2)->toDateString(),
            $real->fresh()->started_at?->toDateString(),
        );
    }

    public function test_employer_dashboard_refreshes_demo_clock_before_rendering_today_count(): void
    {
        $organization = $this->demoOrganization();
        $this->seedDemoCall($organization, 1, now()->subDays(3)->setTime(9, 30), 90);

        $html = Livewire::test(Overview::class)
            ->assertSee('تماس‌های امروز')
            ->html();

        $this->assertSame(1, app(OrganizationCallMetrics::class)->countToday($organization->id));
        $this->assertMatchesRegularExpression('/تماس‌های امروز[\s\S]{0,280}>1</u', $html);
    }

    private function demoOrganization(): Organization
    {
        $employer = User::factory()->employer()->create();
        $organization = Organization::factory()->create([
            'user_id' => $employer->id,
            'is_demo' => true,
        ]);

        OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->employee()->create()->id,
            'first_name' => 'سارا',
            'last_name' => 'کریمی',
            'is_active' => true,
        ]);

        $this->actingAs($employer);

        return $organization;
    }

    private function seedDemoCall(Organization $organization, int $callIndex, $startedAt, int $score): Call
    {
        $employeeId = OrganizationUser::query()->where('organization_id', $organization->id)->value('id')
            ?? OrganizationUser::query()->create([
                'organization_id' => $organization->id,
                'user_id' => User::factory()->employee()->create()->id,
                'first_name' => 'علی',
                'last_name' => 'محمدی',
                'is_active' => true,
            ])->id;

        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employeeId,
            'external_call_id' => "demo-{$organization->id}-call-{$callIndex}",
            'provider_code' => 'demo',
            'source' => ConversationSource::Imported,
            'direction' => 'inbound',
            'caller_number' => '09121234567',
            'receiver_number' => '02112345678',
            'status' => 'completed',
            'processing_status' => CallProcessingStatus::Analyzed,
            'started_at' => $startedAt,
            'ended_at' => $startedAt->copy()->addMinutes(6),
            'conversation_date' => $startedAt,
            'duration_seconds' => 360,
        ]);

        ConversationAnalysis::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employeeId,
            'call_id' => $call->id,
            'source' => ConversationSource::Imported,
            'llm_provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'score' => $score,
            'is_evaluable' => true,
            'summary' => 'خلاصه دمو',
            'sentiment' => AnalysisSentiment::Positive,
            'strengths_json' => ['گوش دادن فعال'],
            'weaknesses_json' => [],
            'next_actions_json' => [],
            'analyzed_at' => $startedAt->copy()->addMinutes(8),
        ]);

        return $call;
    }
}
