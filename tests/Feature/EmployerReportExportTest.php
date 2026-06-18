<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployerReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_employer_can_download_team_performance_csv_export(): void
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        Organization::factory()->create(['user_id' => $employer->id]);

        $response = $this->actingAs($employer)->get(
            route('employer.intelligence.performance.export', ['format' => 'csv', 'preset' => 'last_30'])
        );

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
    }

    public function test_employer_can_download_reports_excel_export(): void
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        Organization::factory()->create(['user_id' => $employer->id]);

        $response = $this->actingAs($employer)->get(
            route('employer.reports.export', ['format' => 'xlsx', 'preset' => 'last_30'])
        );

        $response->assertOk();
        $response->assertHeader('content-disposition');
    }

    public function test_guest_cannot_download_exports(): void
    {
        $this->get(route('employer.reports.export', ['format' => 'csv']))
            ->assertRedirect(route('login'));
    }
}
