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

    public function test_contacts_default_to_twenty_per_page_with_selector(): void
    {
        $this->actingAsEmployer();
        $this->createContacts(55);

        $component = Livewire::test(EmployerContactsIndex::class)
            ->assertDontSee('1 تا')
            ->assertDontSee('Showing')
            ->assertSeeHtml('wire:model.live="perPage"');

        $this->assertCount(20, $component->viewData('contacts'));
        $this->assertSame(55, $component->viewData('contacts')->total());
        $this->assertTrue($component->viewData('contacts')->hasPages());
        $this->assertSame(3, $component->viewData('contacts')->lastPage());
    }

    public function test_page_numbers_use_first_pages_ellipsis_and_last_pages(): void
    {
        $this->actingAsEmployer();
        $this->createContacts(20 * 12);

        $component = Livewire::test(EmployerContactsIndex::class);

        $component->assertSeeHtml('aria-current="page"');
        $component->assertSeeHtml('gotoPage(2,');
        $component->assertSeeHtml('gotoPage(3,');
        $component->assertSeeHtml('>...</span>');
        $component->assertSeeHtml('gotoPage(10,');
        $component->assertSeeHtml('gotoPage(11,');
        $component->assertSeeHtml('gotoPage(12,');
        $component->assertDontSeeHtml('gotoPage(6,');

        $component->call('gotoPage', 6);

        $component->assertSeeHtml('gotoPage(1,');
        $component->assertSeeHtml('gotoPage(12,');
        $component->assertDontSeeHtml('gotoPage(6,');
        $this->assertSame(6, $component->viewData('contacts')->currentPage());
        $this->assertCount(20, $component->viewData('contacts'));
    }

    public function test_employee_contacts_use_the_same_default_page_size(): void
    {
        $this->actingAsEmployee();
        $this->createContacts(53);

        $component = Livewire::test(EmployeeContactsIndex::class)
            ->assertDontSee('1 تا');

        $this->assertCount(20, $component->viewData('contacts'));
        $this->assertTrue($component->viewData('contacts')->hasPages());
    }

    public function test_changing_per_page_updates_page_size(): void
    {
        $this->actingAsEmployer();
        $this->createContacts(55);

        $component = Livewire::test(EmployerContactsIndex::class)
            ->set('perPage', 50);

        $this->assertCount(50, $component->viewData('contacts'));
        $this->assertSame(2, $component->viewData('contacts')->lastPage());
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

        $now = now();
        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $rows[] = [
                'organization_id' => $organization->id,
                'normalized_phone' => sprintf('0912999%04d', $i),
                'phone_number' => sprintf('0912999%04d', $i),
                'name' => sprintf('مخاطب صفحه %03d', $i),
                'identity_confidence' => 0,
                'total_calls' => 0,
                'total_answered_calls' => 0,
                'last_contact_at' => $now->copy()->subDays($i),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            Customer::query()->insert($chunk);
        }
    }
}
