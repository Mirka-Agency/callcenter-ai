<?php

namespace Tests\Feature;

use App\Domain\Platform\Enums\AdminArea;
use App\Domain\Platform\Enums\AnnouncementAudience;
use App\Domain\Platform\Enums\AnnouncementLevel;
use App\Domain\Platform\Enums\LeadStatus;
use App\Livewire\Shared\NpsPrompt;
use App\Models\AdminAuditLog;
use App\Models\AdminNote;
use App\Models\Announcement;
use App\Models\DemoRequest;
use App\Models\NpsResponse;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\Admin\AdminSummaryReportNotification;
use App\Services\Admin\AdminAlerts;
use App\Services\PlatformAnalytics\NpsMetrics;
use App\Services\Sales\LeadConverter;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class AdminOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-10 12:00:00'));
    }

    public function test_admin_areas_gate_pages(): void
    {
        $organization = Organization::factory()->create();
        $support = $this->admin([AdminArea::Support]);

        foreach (['/admin/problem-inbox', '/admin/operations-health', '/admin/announcements', '/admin/organizations/'.$organization->id.'/overview'] as $url) {
            $this->actingAs($support)->get($url)->assertOk();
        }

        foreach (['/admin/lead-pipeline', '/admin/demo-requests', '/admin/business-analytics', '/admin/nps-responses', '/admin/admin-audit-logs'] as $url) {
            $this->actingAs($support)->get($url)->assertForbidden();
        }

        $sales = $this->admin([AdminArea::Sales]);
        $this->actingAs($sales)->get('/admin/lead-pipeline')->assertOk();
        $this->actingAs($sales)->get('/admin/demo-requests')->assertOk();
        $this->actingAs($sales)->get('/admin/operations-health')->assertForbidden();

        $superAdmin = User::factory()->superAdmin()->create();
        foreach (['/admin/admin-audit-logs', '/admin/nps-responses', '/admin/business-analytics', '/admin/lead-pipeline'] as $url) {
            $this->actingAs($superAdmin)->get($url)->assertOk();
        }
    }

    public function test_admin_changes_are_audited_but_customer_changes_are_not(): void
    {
        $organization = Organization::factory()->create(['title' => 'قدیمی']);

        $this->actingAs($admin = User::factory()->superAdmin()->create());
        $organization->update(['title' => 'جدید']);

        $log = AdminAuditLog::query()->where('action', 'updated')->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('قدیمی', $log->changes['before']['title']);
        $this->assertSame('جدید', $log->changes['after']['title']);

        $this->actingAs($organization->employer);
        $organization->update(['title' => 'کارفرما']);

        $this->assertSame(1, AdminAuditLog::query()->where('action', 'updated')->count());
    }

    public function test_lead_attribution_sla_and_conversion(): void
    {
        $this->get('/?utm_source=google&utm_medium=cpc&utm_campaign=autumn');
        $this->assertSame('google', session('lead_attribution.source'));

        $lead = $this->demoRequest();
        $this->assertSame(LeadStatus::New, $lead->lead_status);

        $this->travel(DemoRequest::SLA_HOURS + 1)->hours();
        $this->assertSame(1, DemoRequest::query()->breachingSla()->count());

        $this->actingAs(User::factory()->superAdmin()->create());
        $result = app(LeadConverter::class)->convert($lead, [
            'title' => 'شرکت جدید',
            'employer_name' => 'مدیر',
            'employer_email' => 'owner@example.com',
        ]);

        $lead->refresh();
        $this->assertSame(LeadStatus::Converted, $lead->lead_status);
        $this->assertSame($result['organization']->id, $lead->converted_organization_id);
        $this->assertSame('owner@example.com', $result['organization']->employer->email);
        $this->assertSame(0, DemoRequest::query()->breachingSla()->count());
        $this->assertTrue(AdminAuditLog::query()->where('action', 'lead_converted')->exists());
    }

    public function test_new_leads_notify_sales_admins_once(): void
    {
        $sales = $this->admin([AdminArea::Sales]);
        $support = $this->admin([AdminArea::Support]);

        $this->demoRequest();

        $this->assertSame(1, $sales->notifications()->count());
        $this->assertSame(0, $support->notifications()->count());

        $alerts = app(AdminAlerts::class);
        $this->assertSame(1, $alerts->send(AdminArea::Sales, 'test', dedupeKey: 'same'));
        $this->assertSame(0, $alerts->send(AdminArea::Sales, 'test', dedupeKey: 'same'));
    }

    public function test_organization_overview_supports_notes(): void
    {
        $organization = Organization::factory()->create();
        $support = $this->admin([AdminArea::Support]);
        $this->actingAs($support);

        AdminNote::query()->create([
            'notable_type' => $organization->getMorphClass(),
            'notable_id' => $organization->id,
            'user_id' => $support->id,
            'body' => 'مشتری درخواست تماس داشت',
        ]);

        $this->get('/admin/organizations/'.$organization->id.'/overview')
            ->assertOk()
            ->assertSee('مشتری درخواست تماس داشت');
    }

    public function test_nps_prompt_collects_scores_every_ninety_days(): void
    {
        $employer = User::factory()->employer()->create(['created_at' => now()->subDays(30)]);
        Organization::factory()->create(['user_id' => $employer->id]);
        $newEmployer = User::factory()->employer()->create();
        Organization::factory()->create(['user_id' => $newEmployer->id]);

        $this->assertTrue(NpsPrompt::shouldAsk($employer));
        $this->assertFalse(NpsPrompt::shouldAsk($newEmployer));
        $this->assertFalse(NpsPrompt::shouldAsk(User::factory()->superAdmin()->create()));

        Livewire::actingAs($employer)
            ->test(NpsPrompt::class)
            ->assertSet('visible', true)
            ->call('choose', 9)
            ->set('comment', 'عالی')
            ->call('submit')
            ->assertSet('submitted', true);

        $this->assertSame(9, NpsResponse::query()->sole()->score);
        $this->assertFalse(NpsPrompt::shouldAsk($employer));

        $this->travel(NpsPrompt::SURVEY_INTERVAL_DAYS + 1)->days();
        $this->assertTrue(NpsPrompt::shouldAsk($employer));
    }

    public function test_nps_score_counts_promoters_minus_detractors(): void
    {
        $organization = Organization::factory()->create();

        foreach ([10, 9, 7, 3, null] as $score) {
            NpsResponse::query()->create(['organization_id' => $organization->id, 'user_id' => $organization->user_id, 'score' => $score]);
        }

        $summary = app(NpsMetrics::class)->summary();

        $this->assertSame(25, $summary['score']);
        $this->assertSame(4, $summary['responses']);
        $this->assertSame(1, $summary['skipped']);
        $this->assertEqualsWithDelta(0.8, $summary['response_rate'], 0.0001);
    }

    public function test_announcements_are_shown_to_their_audience_while_active(): void
    {
        $employer = User::factory()->employer()->create();
        $employee = User::factory()->employee()->create();

        Announcement::query()->create(['title' => 'برای کارفرماها', 'body' => 'متن', 'level' => AnnouncementLevel::Info, 'audience' => AnnouncementAudience::Employers, 'is_active' => true]);
        Announcement::query()->create(['title' => 'تمام‌شده', 'body' => 'متن', 'level' => AnnouncementLevel::Warning, 'audience' => AnnouncementAudience::All, 'is_active' => true, 'ends_at' => now()->subDay()]);
        Announcement::query()->create(['title' => 'برای همه', 'body' => 'متن', 'level' => AnnouncementLevel::Maintenance, 'audience' => AnnouncementAudience::All, 'is_active' => true, 'starts_at' => now()->subHour()]);

        $this->assertEqualsCanonicalizing(['برای کارفرماها', 'برای همه'], Announcement::query()->visibleTo($employer)->pluck('title')->all());
        $this->assertSame(['برای همه'], Announcement::query()->visibleTo($employee)->pluck('title')->all());
    }

    public function test_employer_layout_shows_announcements_and_nps_prompt(): void
    {
        $employer = User::factory()->employer()->create(['created_at' => now()->subDays(30)]);
        Organization::factory()->create(['user_id' => $employer->id]);
        Announcement::query()->create(['title' => 'به‌روزرسانی جدید', 'body' => 'متن', 'level' => AnnouncementLevel::Info, 'audience' => AnnouncementAudience::All, 'is_active' => true]);

        $this->actingAs($employer)
            ->get(route('employer.dashboard'))
            ->assertOk()
            ->assertSee('به‌روزرسانی جدید')
            ->assertSee('nps-prompt', false);
    }

    public function test_scheduled_commands_run(): void
    {
        Notification::fake();
        $superAdmin = User::factory()->superAdmin()->create();
        $support = $this->admin([AdminArea::Support]);

        $this->artisan('admin:send-report', ['period' => 'weekly'])->assertSuccessful();
        $this->artisan('admin:send-report', ['period' => 'monthly'])->assertSuccessful();
        $this->artisan('admin:send-report', ['period' => 'daily'])->assertFailed();

        Notification::assertSentTo($superAdmin, AdminSummaryReportNotification::class, 2);
        Notification::assertNotSentTo($support, AdminSummaryReportNotification::class);

        $this->artisan('admin:check-alerts')->assertSuccessful();
        $this->artisan('billing:sync-statuses')->assertSuccessful();
    }

    private function demoRequest(): DemoRequest
    {
        return DemoRequest::query()->create([
            'full_name' => 'علی رضایی',
            'phone' => '09120000000',
            'business_domain' => 'فروش',
            'company_name' => 'شرکت آزمایشی',
        ]);
    }

    /** @param  list<AdminArea>  $areas */
    private function admin(array $areas): User
    {
        return User::factory()->admin()->create(['admin_areas' => array_map(fn (AdminArea $area): string => $area->value, $areas)]);
    }
}
