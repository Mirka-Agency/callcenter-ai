<?php

namespace Tests\Feature;

use App\Application\Crm\CrmManager;
use App\Application\Crm\Deals\Actions\SuggestDealFromAnalysis;
use App\Application\Crm\Jobs\SyncCrmDataJob;
use App\Application\Crm\Services\CrmIntelligenceSyncService;
use App\Domain\Crm\OrganizationCrmMode;
use App\Filament\Resources\Organizations\Pages\EditOrganization;
use App\Filament\Resources\Organizations\RelationManagers\CrmConnectionsRelationManager;
use App\Models\ConversationAnalysis;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Support\Navigation\EmployeeNavigation;
use App\Support\Navigation\EmployerNavigation;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizationCrmModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sets_the_organization_crm_mode(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $employer = User::factory()->employer()->create();
        $organization = Organization::factory()->create([
            'user_id' => $employer->id,
            'title' => 'شرکت آزمایش',
        ]);

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(EditOrganization::class, ['record' => $organization->id])
            ->assertFormSet(['crm_mode' => OrganizationCrmMode::Internal->value])
            ->fillForm(['crm_mode' => OrganizationCrmMode::External->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $organization->refresh();
        $this->assertSame(OrganizationCrmMode::External, $organization->crmMode());

        Livewire::test(CrmConnectionsRelationManager::class, [
            'ownerRecord' => $organization,
            'pageClass' => EditOrganization::class,
        ])->assertOk();

        $organization->update(['crm_mode' => OrganizationCrmMode::Internal]);

        $this->assertFalse(CrmConnectionsRelationManager::canViewForRecord(
            $organization->fresh(),
            EditOrganization::class,
        ));
    }

    public function test_internal_mode_opens_avayar_crm_and_hides_external_connections(): void
    {
        [$employer, $employee] = $this->people();

        $this->actingAs($employer);

        $this->assertSame([
            'employer.crm.overview',
            'employer.customers.index',
            'employer.crm.deals.index',
            'employer.crm.deals.list',
            'employer.crm.reminders.index',
            'employer.crm.products.index',
            'employer.crm.settings',
        ], $this->crmRoutes(EmployerNavigation::items()));

        $this->get(route('employer.crm.overview'))->assertOk();
        $this->get(route('employer.crm.index'))->assertNotFound();
        $this->get(route('employer.customers.index'))->assertOk();
        $this->get(route('employer.crm.deals.list'))->assertOk()->assertSee('فهرست فرصت‌ها', false);
        $this->get(route('employer.crm.reminders.index'))->assertOk()->assertSee('یادآوری‌ها', false);
        $this->get(route('employer.crm.products.index'))->assertOk()->assertSee('محصولات و خدمات', false);
        $this->get(route('employer.crm.tasks.index'))->assertOk();

        $this->actingAs($employee);

        $this->assertSame([
            'employee.crm.overview',
            'employee.customers.index',
            'employee.crm.deals.index',
            'employee.crm.deals.list',
            'employee.crm.reminders.index',
            'employee.crm.products.index',
        ], $this->crmRoutes(EmployeeNavigation::items()));

        $this->get(route('employee.crm.deals.index'))->assertOk();
        $this->get(route('employee.crm.deals.list'))->assertOk()->assertSee('فهرست فرصت‌ها', false);
        $this->get(route('employee.crm.reminders.index'))->assertOk()->assertSee('یادآوری‌ها', false);
        $this->get(route('employee.crm.products.index'))->assertOk()->assertSee('محصولات و خدمات', false);
    }

    public function test_external_mode_opens_only_the_external_connection(): void
    {
        [$employer, $employee, $organization] = $this->people(withOrganization: true);
        $organization->update(['crm_mode' => OrganizationCrmMode::External]);

        $this->actingAs($employer);

        $this->assertSame([
            'employer.customers.index',
            'employer.crm.index',
        ], $this->crmRoutes(EmployerNavigation::items()));

        $this->get(route('employer.crm.index'))->assertOk();
        $this->get(route('employer.crm.deals.index'))->assertNotFound();
        $this->get(route('employer.crm.products.index'))->assertNotFound();
        $this->get(route('employer.customers.index'))->assertOk();

        $this->actingAs($employee);

        $this->assertSame([
            'employee.customers.index',
        ], $this->crmRoutes(EmployeeNavigation::items()));

        $this->get(route('employee.crm.overview'))->assertNotFound();
        $this->get(route('employee.customers.index'))->assertOk();
    }

    public function test_analysis_side_effects_follow_the_selected_mode(): void
    {
        $internal = Organization::factory()->create();
        $external = Organization::factory()->create(['crm_mode' => OrganizationCrmMode::External]);
        $suggestions = app(SuggestDealFromAnalysis::class);
        $sync = app(CrmIntelligenceSyncService::class);

        $internalAnalysis = $this->analysisFor($internal);
        $externalAnalysis = $this->analysisFor($external);

        $this->assertNull($suggestions->handle($externalAnalysis, 1));
        $this->assertSame(0, $sync->syncAnalysis($internalAnalysis));

        (new SyncCrmDataJob($internal->id))->handle(app(CrmManager::class));
        $this->assertSame(OrganizationCrmMode::Internal, $internal->fresh()->crmMode());
    }

    /** @return array{0: User, 1: User, 2?: Organization} */
    private function people(bool $withOrganization = false): array
    {
        $employer = User::factory()->employer()->create();
        $organization = Organization::factory()->create(['user_id' => $employer->id]);
        $employee = User::factory()->employee()->create();
        OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $employee->id,
            'first_name' => 'نگار',
            'last_name' => 'کریمی',
            'is_active' => true,
        ]);

        return $withOrganization
            ? [$employer, $employee, $organization]
            : [$employer, $employee];
    }

    /** @param array<int, array<string, mixed>> $items */
    private function crmRoutes(array $items): array
    {
        return array_column(collect($items)->firstWhere('label', 'CRM')['children'], 'route');
    }

    private function analysisFor(Organization $organization): ConversationAnalysis
    {
        $analysis = new ConversationAnalysis;
        $analysis->organization_id = $organization->id;
        $analysis->call_id = 1;
        $analysis->is_personal = false;

        return $analysis;
    }
}
