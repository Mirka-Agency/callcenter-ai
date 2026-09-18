<?php

namespace Tests\Feature;

use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Enums\UserRole;
use App\Livewire\Employer\Intelligence\Index as IntelligenceIndex;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AttentionCallsQuickFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_analysis_page_shows_attention_quick_filter_next_to_missed_calls(): void
    {
        $this->actingAsEmployer();

        $html = Livewire::test(IntelligenceIndex::class)->html();

        $this->assertStringContainsString('تماس‌های از دست رفته', $html);
        $this->assertStringContainsString('تماس‌های نیازمند توجه', $html);
        $this->assertTrue(
            mb_strpos($html, 'تماس‌های از دست رفته') < mb_strpos($html, 'تماس‌های نیازمند توجه'),
        );
    }

    public function test_attention_quick_filter_lists_only_calls_flagged_by_ai(): void
    {
        $organization = $this->actingAsEmployer();
        $employee = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
            'first_name' => 'Ali',
            'last_name' => 'Agent',
            'is_active' => true,
        ]);

        $this->seedAnalysis($organization, $employee, 'تماس عادی بدون اعتراض', false);
        $this->seedAnalysis($organization, $employee, 'مشتری به عملکرد کارشناس اعتراض دارد', true, [
            'needed' => true,
            'categories' => ['agent'],
            'reason' => 'مشتری به عملکرد کارشناس اعتراض دارد',
        ]);

        Livewire::test(IntelligenceIndex::class)
            ->assertSee('تماس عادی بدون اعتراض')
            ->assertSee('مشتری به عملکرد کارشناس اعتراض دارد')
            ->call('applyQuickFilter', 'attention')
            ->assertSet('needsAttention', true)
            ->assertSee('مشتری به عملکرد کارشناس اعتراض دارد')
            ->assertDontSee('تماس عادی بدون اعتراض')
            ->assertSee('نیازمند توجه ×');
    }

    private function actingAsEmployer(): Organization
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $employer->id]);

        $this->actingAs($employer);

        return $organization;
    }

    /** @param  array<string, mixed>|null  $attention */
    private function seedAnalysis(
        Organization $organization,
        OrganizationUser $employee,
        string $summary,
        bool $needsAttention,
        ?array $attention = null,
    ): void {
        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'novatel',
            'external_call_id' => uniqid('call-', true),
            'direction' => 'inbound',
            'caller_number' => '09120000000',
            'receiver_number' => '02100000000',
            'status' => 'completed',
            'processing_status' => 'analyzed',
            'duration_seconds' => 180,
            'started_at' => now(),
        ]);

        ConversationAnalysis::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'call_id' => $call->id,
            'source' => ConversationSource::Voip,
            'llm_provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'score' => 70,
            'is_evaluable' => true,
            'summary' => $summary,
            'sentiment' => AnalysisSentiment::Neutral,
            'strengths_json' => [],
            'weaknesses_json' => [],
            'next_actions_json' => [],
            'needs_attention' => $needsAttention,
            'attention_json' => $attention,
            'analyzed_at' => now(),
        ]);
    }
}
