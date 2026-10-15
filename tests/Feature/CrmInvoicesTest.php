<?php

namespace Tests\Feature;

use App\Application\Crm\Invoices\Actions\CreateInvoice;
use App\Application\Crm\Invoices\Actions\IssueInvoice;
use App\Application\Crm\Invoices\Actions\UpdateInvoice;
use App\Application\Crm\Products\Actions\CreateProduct;
use App\Domain\Crm\Invoices\InvoiceDocumentType;
use App\Domain\Crm\Invoices\InvoiceStatus;
use App\Domain\Crm\OrganizationCrmMode;
use App\Domain\Crm\Products\ProductType;
use App\Enums\UserRole;
use App\Livewire\Crm\Invoices\Index as InvoicesIndex;
use App\Livewire\Crm\Invoices\Show as InvoicesShow;
use App\Models\CrmInvoice;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CrmInvoicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoices_page_lists_documents(): void
    {
        [$organization, $manager] = $this->organization();

        $invoice = app(CreateInvoice::class)->handle($organization->id, $manager, [
            'document_type' => InvoiceDocumentType::Proforma->value,
        ]);

        app(UpdateInvoice::class)->handle($invoice, $manager, [
            'buyer_name' => 'علی رضایی',
            'tax_percent' => 10,
            'lines' => [
                [
                    'description' => 'مشاوره',
                    'quantity' => 1,
                    'unit_label' => 'ساعت',
                    'unit_price_rials' => 1_000_000,
                ],
            ],
        ]);

        $this->actingAs($manager)
            ->get(route('employer.crm.invoices.index'))
            ->assertOk()
            ->assertSee('فاکتور و پیش فاکتور', false)
            ->assertSee('علی رضایی', false)
            ->assertSee('پیش‌فاکتور', false);
    }

    public function test_can_create_draft_and_edit_from_livewire(): void
    {
        [$organization, $manager] = $this->organization();

        $customer = Customer::query()->create([
            'organization_id' => $organization->id,
            'name' => 'سارا محمدی',
            'phone_number' => '09121234567',
            'normalized_phone' => '989121234567',
        ]);

        Livewire::actingAs($manager)
            ->test(InvoicesIndex::class)
            ->call('openCreate')
            ->set('documentType', InvoiceDocumentType::Invoice->value)
            ->set('customerId', (string) $customer->id)
            ->call('createDraft')
            ->assertHasNoErrors()
            ->assertRedirect();

        $invoice = CrmInvoice::query()
            ->where('organization_id', $organization->id)
            ->first();

        $this->assertNotNull($invoice);
        $this->assertSame(InvoiceDocumentType::Invoice, $invoice->document_type);
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertSame('سارا محمدی', $invoice->buyer_name);
        $this->assertSame((int) $customer->id, (int) $invoice->customer_id);

        Livewire::actingAs($manager)
            ->test(InvoicesShow::class, ['invoice' => $invoice->id])
            ->set('buyerCompany', 'شرکت نمونه')
            ->set('taxPercent', '9')
            ->set('lines', [
                [
                    'key' => 'line_1',
                    'crm_product_id' => null,
                    'description' => 'خدمت پشتیبانی',
                    'quantity' => '2',
                    'unit_label' => 'ماه',
                    'unit_price_rials' => '500000',
                ],
            ])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('خدمت پشتیبانی', false);

        $invoice->refresh();
        $this->assertSame('شرکت نمونه', $invoice->buyer_company);
        $this->assertSame(9.0, (float) $invoice->tax_percent);
        $this->assertSame(1_000_000, (int) $invoice->subtotal_rials);
        $this->assertSame(90_000, (int) $invoice->tax_rials);
        $this->assertSame(1_090_000, (int) $invoice->total_rials);
        $this->assertCount(1, $invoice->lines);
    }

    public function test_type_filter_works(): void
    {
        [$organization, $manager] = $this->organization();

        app(CreateInvoice::class)->handle($organization->id, $manager, [
            'document_type' => InvoiceDocumentType::Proforma->value,
            'customer_id' => null,
        ]);
        $proforma = CrmInvoice::query()->where('organization_id', $organization->id)->latest('id')->first();
        $proforma->update(['buyer_name' => 'پیش‌فاکتور آلفا']);

        app(CreateInvoice::class)->handle($organization->id, $manager, [
            'document_type' => InvoiceDocumentType::Invoice->value,
        ]);
        $invoice = CrmInvoice::query()->where('organization_id', $organization->id)->latest('id')->first();
        $invoice->update(['buyer_name' => 'فاکتور بتا']);

        Livewire::actingAs($manager)
            ->test(InvoicesIndex::class)
            ->call('setType', 'proforma')
            ->assertSee('پیش‌فاکتور آلفا', false)
            ->assertDontSee('فاکتور بتا', false)
            ->call('setType', 'invoice')
            ->assertSee('فاکتور بتا', false)
            ->assertDontSee('پیش‌فاکتور آلفا', false);
    }

    public function test_can_add_catalog_line_issue_and_delete(): void
    {
        [$organization, $manager] = $this->organization();

        $product = app(CreateProduct::class)->handle($organization->id, $manager, [
            'name' => 'بسته پیامک',
            'type' => ProductType::Product->value,
            'price_tomans' => 100_000,
            'unit_label' => 'عدد',
        ]);

        $invoice = app(CreateInvoice::class)->handle($organization->id, $manager, [
            'document_type' => InvoiceDocumentType::Proforma->value,
        ]);

        Livewire::actingAs($manager)
            ->test(InvoicesShow::class, ['invoice' => $invoice->id])
            ->set('catalogProductId', (string) $product->id)
            ->call('addFromCatalog')
            ->call('save')
            ->assertHasNoErrors();

        $invoice->refresh();
        $this->assertCount(1, $invoice->lines);
        $this->assertSame(1_000_000, (int) $invoice->lines->first()->unit_price_rials);
        $this->assertSame('بسته پیامک', $invoice->lines->first()->description);

        Livewire::actingAs($manager)
            ->test(InvoicesShow::class, ['invoice' => $invoice->id])
            ->call('issue')
            ->assertHasNoErrors();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertNotNull($invoice->number);
        $this->assertNotNull($invoice->issued_at);

        Livewire::actingAs($manager)
            ->test(InvoicesShow::class, ['invoice' => $invoice->id])
            ->set('buyerName', 'تغییر غیرمجاز')
            ->call('save')
            ->assertHasErrors('status');

        Livewire::actingAs($manager)
            ->test(InvoicesShow::class, ['invoice' => $invoice->id])
            ->call('remove')
            ->assertRedirect(route('employer.crm.invoices.index'));

        $this->assertSoftDeleted($invoice);
    }

    public function test_issue_assigns_sequential_numbers(): void
    {
        [$organization, $manager] = $this->organization();

        $first = app(CreateInvoice::class)->handle($organization->id, $manager, [
            'document_type' => InvoiceDocumentType::Invoice->value,
        ]);
        app(UpdateInvoice::class)->handle($first, $manager, [
            'lines' => [['description' => 'الف', 'quantity' => 1, 'unit_price_rials' => 1000]],
        ]);
        $first = app(IssueInvoice::class)->handle($first->fresh(), $manager);

        $second = app(CreateInvoice::class)->handle($organization->id, $manager, [
            'document_type' => InvoiceDocumentType::Invoice->value,
        ]);
        app(UpdateInvoice::class)->handle($second, $manager, [
            'lines' => [['description' => 'ب', 'quantity' => 1, 'unit_price_rials' => 2000]],
        ]);
        $second = app(IssueInvoice::class)->handle($second->fresh(), $manager);

        $this->assertMatchesRegularExpression('/^INV-\d{4}-0001$/', (string) $first->number);
        $this->assertMatchesRegularExpression('/^INV-\d{4}-0002$/', (string) $second->number);
    }

    public function test_cross_tenant_invoice_is_not_visible(): void
    {
        [$organization, $manager] = $this->organization();
        [$otherOrganization, $otherManager] = $this->organization();

        $foreign = app(CreateInvoice::class)->handle($otherOrganization->id, $otherManager, [
            'document_type' => InvoiceDocumentType::Proforma->value,
        ]);
        $foreign->update(['buyer_name' => 'سند سازمان دیگر']);

        Livewire::actingAs($manager)
            ->test(InvoicesIndex::class)
            ->assertDontSee('سند سازمان دیگر', false);

        $this->actingAs($manager)
            ->get(route('employer.crm.invoices.show', $foreign))
            ->assertNotFound();
    }

    public function test_external_crm_blocks_invoices_route(): void
    {
        [$organization, $manager] = $this->organization();
        $organization->update(['crm_mode' => OrganizationCrmMode::External]);

        $this->actingAs($manager)
            ->get(route('employer.crm.invoices.index'))
            ->assertNotFound();
    }

    /** @return array{0: Organization, 1: User, 2: OrganizationUser} */
    private function organization(): array
    {
        $manager = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create([
            'user_id' => $manager->id,
            'crm_mode' => OrganizationCrmMode::Internal,
        ]);
        $agent = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
            'first_name' => 'سارا',
            'last_name' => 'نوری',
            'is_active' => true,
        ]);

        return [$organization, $manager, $agent];
    }
}
