<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Employee\Uploads\Index as EmployeeUploadsIndex;
use App\Livewire\Employer\ManualAnalyses\Index as EmployerManualAnalysesIndex;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\AiBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManualUploadSampleConversationsRemovedTest extends TestCase
{
    use RefreshDatabase;

    public function test_employer_manual_upload_page_does_not_show_sample_conversations(): void
    {
        $this->actingAsEmployer();
        $this->fakeWalletOverview();

        Livewire::test(EmployerManualAnalysesIndex::class)
            ->assertDontSee('نمونه مکالمه')
            ->assertDontSee('پیگیری پیشنهاد فروش')
            ->assertDontSee('تمدید اشتراک سالانه')
            ->assertDontSee('تحلیل این نمونه');

        $this->assertFalse(method_exists(EmployerManualAnalysesIndex::class, 'submitSampleForAnalysis'));
    }

    public function test_employee_upload_page_does_not_show_sample_conversations(): void
    {
        $this->actingAsEmployee();
        $this->fakeWalletOverview();

        Livewire::test(EmployeeUploadsIndex::class)
            ->assertDontSee('نمونه مکالمه')
            ->assertDontSee('پیگیری پیشنهاد فروش')
            ->assertDontSee('تمدید اشتراک سالانه')
            ->assertDontSee('تحلیل این نمونه');

        $this->assertFalse(method_exists(EmployeeUploadsIndex::class, 'submitSampleForAnalysis'));
    }

    private function actingAsEmployer(): Organization
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $employer->id]);
        $this->actingAs($employer);

        return $organization;
    }

    private function actingAsEmployee(): Organization
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $employer->id]);
        $employee = User::factory()->create(['role' => UserRole::Employee]);

        OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $employee->id,
            'first_name' => 'علی',
            'last_name' => 'محمدی',
            'is_active' => true,
        ]);

        $this->actingAs($employee);

        return $organization;
    }

    private function fakeWalletOverview(): void
    {
        $this->mock(AiBillingService::class, function ($mock) {
            $mock->shouldReceive('walletOverview')->andReturn([
                'balance' => 1_000_000,
                'currency' => 'IRR',
            ]);
        });
    }
}
