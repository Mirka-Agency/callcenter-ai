<?php

namespace Tests\Feature;

use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Enums\UserRole;
use App\Livewire\Employer\Intelligence\Show as IntelligenceShow;
use App\Livewire\Employer\ManualAnalyses\Index as EmployerManualAnalysesIndex;
use App\Livewire\Employer\ManualAnalyses\Show as ManualAnalysesShow;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\AiBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

class ManualAnalysisEmployeeRequiredTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_upload_asks_for_an_employee_before_analysis(): void
    {
        $organization = $this->actingAsEmployer();
        $this->fakeWalletOverview();

        Livewire::test(EmployerManualAnalysesIndex::class)
            ->assertSee('کارشناس این تماس')
            ->set('audio', UploadedFile::fake()->create('call.mp3', 100, 'audio/mpeg'))
            ->call('submitForAnalysis')
            ->assertHasErrors(['employeeId'])
            ->assertSee('برای شروع تحلیل، کارشناس این تماس را انتخاب کنید.');

        $this->assertDatabaseMissing('calls', [
            'organization_id' => $organization->id,
            'source' => ConversationSource::ManualUpload->value,
        ]);
    }

    public function test_reanalyze_without_employee_asks_for_one(): void
    {
        [$organization, $employer] = $this->employerWithOrganization();
        $call = $this->manualCall($organization);
        $analysis = $this->analysisFor($organization, $call);

        $this->actingAs($employer);
        Bus::fake();

        Livewire::test(IntelligenceShow::class, ['analysis' => $analysis])
            ->call('reanalyze')
            ->assertSee(__('ui.intelligence.reanalyze_one_employee_required'));

        Bus::assertNothingDispatched();
    }

    public function test_assigning_employee_on_manual_analysis_updates_metrics_owner(): void
    {
        [$organization, $employer] = $this->employerWithOrganization();
        $employee = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
            'first_name' => 'سارا',
            'last_name' => 'کریمی',
            'is_active' => true,
        ]);
        $call = $this->manualCall($organization);
        $analysis = $this->analysisFor($organization, $call);

        $this->actingAs($employer);

        Livewire::test(ManualAnalysesShow::class, ['upload' => $call])
            ->set('assignEmployeeId', $employee->id)
            ->call('assignCallEmployee')
            ->assertHasNoErrors();

        $this->assertSame($employee->id, $call->fresh()->organization_user_id);
        $this->assertSame($employee->id, $analysis->fresh()->organization_user_id);
        $this->assertDatabaseHas('employee_performance_snapshots', [
            'organization_user_id' => $employee->id,
            'organization_id' => $organization->id,
        ]);
    }

    private function actingAsEmployer(): Organization
    {
        [$organization, $employer] = $this->employerWithOrganization();
        $this->actingAs($employer);

        return $organization;
    }

    /** @return array{0: Organization, 1: User} */
    private function employerWithOrganization(): array
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $employer->id]);

        return [$organization, $employer];
    }

    private function manualCall(Organization $organization): Call
    {
        return Call::query()->create([
            'organization_id' => $organization->id,
            'provider_code' => 'manual',
            'external_call_id' => 'manual-'.uniqid(),
            'direction' => 'inbound',
            'caller_number' => 'unknown',
            'receiver_number' => 'manual-upload',
            'status' => 'completed',
            'source' => ConversationSource::ManualUpload,
            'organization_user_id' => null,
        ]);
    }

    private function analysisFor(Organization $organization, Call $call): ConversationAnalysis
    {
        return ConversationAnalysis::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => null,
            'call_id' => $call->id,
            'source' => ConversationSource::ManualUpload,
            'llm_provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'score' => 80,
            'is_evaluable' => true,
            'summary' => 'خلاصه آزمایشی',
            'sentiment' => AnalysisSentiment::Neutral,
            'strengths_json' => ['پیگیری واضح'],
            'weaknesses_json' => [],
            'next_actions_json' => [],
            'cost' => 0,
            'total_tokens' => 0,
            'analyzed_at' => now(),
        ]);
    }

    private function fakeWalletOverview(): void
    {
        $this->mock(AiBillingService::class, function ($mock): void {
            $mock->shouldReceive('walletOverview')->andReturn([
                'balance' => 1_000_000,
                'currency' => 'IRR',
            ]);
        });
    }
}
