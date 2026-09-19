<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployerReportsRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_employer_panel_does_not_expose_management_reports(): void
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        Organization::factory()->create(['user_id' => $employer->id]);

        $this->actingAs($employer)
            ->get(route('employer.dashboard'))
            ->assertOk()
            ->assertDontSee('گزارش‌های مدیریتی');

        $this->actingAs($employer)
            ->get(route('employer.intelligence.index'))
            ->assertOk()
            ->assertDontSee('گزارش‌های مدیریتی');

        $this->actingAs($employer)
            ->get('/app/reports')
            ->assertNotFound();
    }
}
