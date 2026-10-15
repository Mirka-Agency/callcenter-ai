<?php

namespace Tests\Feature;

use App\Application\Crm\Deals\Actions\EnsureDefaultPipeline;
use App\Application\Crm\Invoices\Actions\CreateInvoice;
use App\Application\Crm\Settings\OrganizationCrmPreferencesService;
use App\Domain\Crm\Invoices\InvoiceDocumentType;
use App\Domain\Crm\Tasks\TaskSource;
use App\Domain\Crm\Tasks\TaskStatus;
use App\Enums\UserRole;
use App\Livewire\Crm\Settings as CrmSettings;
use App\Models\CrmTask;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CrmOrganizationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_employer_can_save_general_crm_settings_and_tax_applies_to_invoices(): void
    {
        [$organization, $manager] = $this->organization();
        $pipeline = app(EnsureDefaultPipeline::class)->handle($organization->id);

        $this->actingAs($manager);

        Livewire::test(CrmSettings::class)
            ->assertSee('تنظیمات مشتریان و پیگیری')
            ->set('callSummaryPipelineId', (string) $pipeline->id)
            ->set('aiCatalogSearchEnabled', false)
            ->set('requireNationalIdForNewNumbers', true)
            ->set('aiProformaEnabled', false)
            ->set('legalName', 'شرکت آواپرداز')
            ->set('tradeName', 'آوایار')
            ->set('nationalId', '10101234567')
            ->set('defaultTaxPercent', '9')
            ->call('saveSettings')
            ->assertHasNoErrors();

        $organization->refresh();
        $prefs = app(OrganizationCrmPreferencesService::class)->forOrganization($organization);

        $this->assertSame((int) $pipeline->id, $prefs->callSummaryPipelineId);
        $this->assertFalse($prefs->aiCatalogSearchEnabled);
        $this->assertTrue($prefs->requireNationalIdForNewNumbers);
        $this->assertFalse($prefs->aiProformaEnabled);
        $this->assertSame('شرکت آواپرداز', $prefs->companyIdentity['legal_name']);
        $this->assertSame('آوایار', $prefs->companyIdentity['trade_name']);
        $this->assertSame(9.0, $prefs->defaultTaxPercent);

        $invoice = app(CreateInvoice::class)->handle($organization->id, $manager, [
            'document_type' => InvoiceDocumentType::Proforma->value,
            'buyer_name' => 'خریدار',
        ]);

        $this->assertSame(9.0, (float) $invoice->tax_percent);
    }

    public function test_disabling_sales_follow_up_cancels_open_sales_follow_up_tasks(): void
    {
        [$organization, $manager] = $this->organization();

        $open = CrmTask::query()->create([
            'organization_id' => $organization->id,
            'title' => 'پیگیری فروش',
            'status' => TaskStatus::Pending,
            'priority' => 'normal',
            'source' => TaskSource::SalesFollowUp,
            'created_by_user_id' => $manager->id,
            'due_at' => now()->addDays(3),
        ]);

        $manual = CrmTask::query()->create([
            'organization_id' => $organization->id,
            'title' => 'یادآور دستی',
            'status' => TaskStatus::Pending,
            'priority' => 'normal',
            'source' => TaskSource::Manual,
            'created_by_user_id' => $manager->id,
            'due_at' => now()->addDays(2),
        ]);

        $this->actingAs($manager);

        Livewire::test(CrmSettings::class)
            ->set('salesFollowUpEnabled', true)
            ->set('salesFollowUpDefaultDays', '3, 10')
            ->set('salesFollowUpMaxCount', '2')
            ->set('salesFollowUpMinDayInterval', '3')
            ->set('salesFollowUpEarliestDays', '2')
            ->set('salesFollowUpReplanIntervalDays', '7')
            ->call('saveSalesFollowUp')
            ->assertHasNoErrors()
            ->set('salesFollowUpEnabled', false)
            ->call('saveSalesFollowUp')
            ->assertHasNoErrors();

        $this->assertSame(TaskStatus::Cancelled, $open->fresh()->status);
        $this->assertSame(TaskStatus::Pending, $manual->fresh()->status);

        $prefs = app(OrganizationCrmPreferencesService::class)->forOrganization($organization->fresh());
        $this->assertFalse($prefs->salesFollowUpEnabled);
        $this->assertSame([3, 10], $prefs->salesFollowUpDefaultDays);
    }

    public function test_rejects_pipeline_from_another_organization(): void
    {
        [$organization, $manager] = $this->organization();
        [$other] = $this->organization();
        $foreignPipeline = app(EnsureDefaultPipeline::class)->handle($other->id);

        $this->actingAs($manager);

        Livewire::test(CrmSettings::class)
            ->set('callSummaryPipelineId', (string) $foreignPipeline->id)
            ->set('defaultTaxPercent', '10')
            ->call('saveSettings')
            ->assertHasErrors(['callSummaryPipelineId']);

        $this->assertNull(
            app(OrganizationCrmPreferencesService::class)->forOrganization($organization->fresh())->callSummaryPipelineId
        );
    }

    /** @return array{0: Organization, 1: User} */
    private function organization(): array
    {
        $manager = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $manager->id]);

        return [$organization, $manager];
    }
}
