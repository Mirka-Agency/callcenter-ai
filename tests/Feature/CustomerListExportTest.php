<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Employer\Customers\Companies\Index as CompaniesIndex;
use App\Livewire\Employer\Customers\Contacts\Index as ContactsIndex;
use App\Models\Customer;
use App\Models\CustomerCompany;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerListExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_contacts_page_shows_excel_and_pdf_export_choices(): void
    {
        $this->actingAsEmployer();

        Livewire::test(ContactsIndex::class)
            ->assertSee('دریافت لیست')
            ->assertSee('Excel')
            ->assertSee('PDF')
            ->assertSeeHtml(route('employer.customers.contacts.export', ['format' => 'xlsx']))
            ->assertSeeHtml(route('employer.customers.contacts.export', ['format' => 'pdf']));
    }

    public function test_companies_page_shows_excel_and_pdf_export_choices(): void
    {
        $this->actingAsEmployer();

        Livewire::test(CompaniesIndex::class)
            ->assertSee('دریافت لیست')
            ->assertSee('Excel')
            ->assertSee('PDF')
            ->assertSeeHtml(route('employer.customers.companies.export', ['format' => 'xlsx']));
    }

    public function test_employer_can_download_contacts_excel_without_other_organization_rows(): void
    {
        $organization = $this->actingAsEmployer();
        $this->createContact($organization, 'مخاطب خودی', '09121110001');

        $foreign = Organization::factory()->create();
        $this->createContact($foreign, 'مخاطب خارجی محرمانه', '09121110002');

        $excel = $this->get(route('employer.customers.contacts.export', ['format' => 'xlsx']));
        $excel->assertOk();
        $excel->assertHeader('content-disposition');
        $this->assertStringContainsString('.xlsx', (string) $excel->headers->get('content-disposition'));

        $pdf = $this->get(route('employer.customers.contacts.export', ['format' => 'pdf']));
        $pdf->assertOk();
        $content = $pdf->streamedContent();
        $this->assertStringContainsString('مخاطب خودی', $content);
        $this->assertStringNotContainsString('مخاطب خارجی محرمانه', $content);
    }

    public function test_employer_can_download_companies_pdf_and_search_is_applied(): void
    {
        $organization = $this->actingAsEmployer();
        $this->createCompany($organization, 'شرکت آلفا');
        $this->createCompany($organization, 'شرکت بتا جدا');

        $response = $this->get(route('employer.customers.companies.export', [
            'format' => 'pdf',
            'search' => 'آلفا',
        ]));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('لیست شرکت‌ها', $content);
        $this->assertStringContainsString('شرکت آلفا', $content);
        $this->assertStringNotContainsString('شرکت بتا جدا', $content);
    }

    public function test_employee_can_download_contacts_pdf_for_their_organization(): void
    {
        $organization = $this->actingAsEmployee();
        $this->createContact($organization, 'مخاطب فضای کار', '09121110003');

        $response = $this->get(route('employee.customers.contacts.export', ['format' => 'pdf']));

        $response->assertOk();
        $this->assertStringContainsString('مخاطب فضای کار', $response->streamedContent());
    }

    public function test_guest_cannot_download_customer_exports(): void
    {
        $this->get(route('employer.customers.contacts.export', ['format' => 'xlsx']))
            ->assertRedirect(route('login'));

        $this->get(route('employee.customers.companies.export', ['format' => 'pdf']))
            ->assertRedirect(route('login'));
    }

    public function test_invalid_export_format_is_not_found(): void
    {
        $this->actingAsEmployer();

        $this->get('/app/customers/contacts/export/csv')->assertNotFound();
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

    private function createCompany(Organization $organization, string $name): CustomerCompany
    {
        return CustomerCompany::query()->create([
            'organization_id' => $organization->id,
            'name' => $name,
        ]);
    }

    private function createContact(Organization $organization, string $name, string $phone): Customer
    {
        return Customer::query()->create([
            'organization_id' => $organization->id,
            'normalized_phone' => $phone,
            'phone_number' => $phone,
            'name' => $name,
        ]);
    }
}
