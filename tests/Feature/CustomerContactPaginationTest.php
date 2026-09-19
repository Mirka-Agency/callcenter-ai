<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Employee\Customers\Contacts\Index as EmployeeContactsIndex;
use App\Livewire\Employer\Customers\Contacts\Index as EmployerContactsIndex;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerContactPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_contacts_default_to_ten_per_page_with_editable_range(): void
    {
        $this->actingAsEmployer();
        $this->createContacts(16);

        $component = Livewire::test(EmployerContactsIndex::class)
            ->assertSet('perPage', 10)
            ->assertSee('1 تا')
            ->assertDontSee('Showing');

        $this->assertCount(10, $component->viewData('contacts'));
        $this->assertSame(16, $component->viewData('contacts')->total());
        $this->assertTrue($component->viewData('contacts')->hasPages());
        $component->assertSeeHtml('wire:model.blur="perPage"');
    }

    public function test_user_can_change_how_many_contacts_appear_on_the_page(): void
    {
        $this->actingAsEmployer();
        $this->createContacts(25);

        $component = Livewire::test(EmployerContactsIndex::class)
            ->call('gotoPage', 2)
            ->assertSet('paginators.page', 2)
            ->set('perPage', 20)
            ->assertSet('perPage', 20)
            ->assertSet('paginators.page', 1);

        $this->assertCount(20, $component->viewData('contacts'));
        $this->assertTrue($component->viewData('contacts')->hasPages());
    }

    public function test_invalid_per_page_falls_back_and_url_hydrates_known_value(): void
    {
        $this->actingAsEmployer();
        $this->createContacts(12);

        Livewire::test(EmployerContactsIndex::class)
            ->set('perPage', 0)
            ->assertSet('perPage', 10);

        Livewire::test(EmployerContactsIndex::class)
            ->set('perPage', 1000)
            ->assertSet('perPage', 100);

        Livewire::withQueryParams(['per_page' => '25'])
            ->test(EmployerContactsIndex::class)
            ->assertSet('perPage', 25);
    }

    public function test_employee_contacts_use_the_same_per_page_control(): void
    {
        $this->actingAsEmployee();
        $this->createContacts(14);

        $component = Livewire::test(EmployeeContactsIndex::class)
            ->assertSet('perPage', 10)
            ->assertSee('1 تا')
            ->set('perPage', 14);

        $this->assertCount(14, $component->viewData('contacts'));
        $this->assertFalse($component->viewData('contacts')->hasPages());
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

    private function createContacts(int $count): void
    {
        $organization = auth()->user()->role === UserRole::Employer
            ? Organization::query()->where('user_id', auth()->id())->firstOrFail()
            : OrganizationUser::query()->where('user_id', auth()->id())->firstOrFail()->organization;

        for ($i = 1; $i <= $count; $i++) {
            Customer::query()->create([
                'organization_id' => $organization->id,
                'normalized_phone' => sprintf('0912999%04d', $i),
                'phone_number' => sprintf('0912999%04d', $i),
                'name' => sprintf('مخاطب صفحه %02d', $i),
                'last_contact_at' => now()->subDays($i),
            ]);
        }
    }
}
