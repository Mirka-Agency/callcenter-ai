<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Employer\Customers\Contacts\Create as ContactCreate;
use App\Models\Customer;
use App\Models\CustomerCompany;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerContactCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_employer_can_create_a_contact_from_the_customers_hub_action(): void
    {
        $organization = $this->actingAsEmployer();
        $company = CustomerCompany::query()->create([
            'organization_id' => $organization->id,
            'name' => 'شرکت همراه',
        ]);

        Livewire::test(ContactCreate::class)
            ->assertSee('شخص جدید')
            ->set('name', 'علی رضایی')
            ->set('phone_number', '09123334455')
            ->set('email', 'ali@example.com')
            ->set('job_title', 'مدیر خرید')
            ->set('customer_company_id', $company->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('employer.customers.show', Customer::query()->first()));

        $this->assertDatabaseHas('customers', [
            'organization_id' => $organization->id,
            'name' => 'علی رضایی',
            'phone_number' => '09123334455',
            'normalized_phone' => '09123334455',
            'email' => 'ali@example.com',
            'job_title' => 'مدیر خرید',
            'customer_company_id' => $company->id,
            'company_name' => 'شرکت همراه',
        ]);
    }

    public function test_duplicate_phone_in_the_same_organization_is_rejected(): void
    {
        $organization = $this->actingAsEmployer();
        Customer::query()->create([
            'organization_id' => $organization->id,
            'name' => 'شخص قبلی',
            'phone_number' => '09120001111',
            'normalized_phone' => '09120001111',
        ]);

        Livewire::test(ContactCreate::class)
            ->set('name', 'شخص تکراری')
            ->set('phone_number', '09120001111')
            ->call('save')
            ->assertHasErrors(['phone_number']);

        $this->assertSame(1, Customer::query()->where('organization_id', $organization->id)->count());
    }

    private function actingAsEmployer(): Organization
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $employer->id]);

        $this->actingAs($employer);

        return $organization;
    }
}
