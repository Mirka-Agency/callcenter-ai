<?php

namespace Tests\Feature;

use App\Domain\Crm\Enums\CrmProviderCode;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Enums\UserRole;
use App\Infrastructure\Voip\Adapters\SimotelVoipAdapter;
use App\Livewire\Employer\Organization\Profile;
use App\Models\CrmProvider;
use App\Models\Organization;
use App\Models\OrganizationCrmConnection;
use App\Models\OrganizationVoipConnection;
use App\Models\User;
use App\Models\VoipProvider;
use App\Support\Navigation\EmployerNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmployerOrganizationProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_lists_organization_profile_and_drops_company_holidays(): void
    {
        $routes = collect(EmployerNavigation::items())->pluck('route');

        $this->assertTrue($routes->contains('employer.organization.profile'));
        $this->assertFalse($routes->contains('employer.holidays.index'));

        $employer = User::factory()->create(['role' => UserRole::Employer]);
        Organization::factory()->create([
            'user_id' => $employer->id,
            'title' => 'شرکت نمونه',
        ]);

        $this->actingAs($employer)
            ->get(route('employer.organization.profile'))
            ->assertOk()
            ->assertSee('پروفایل سازمان')
            ->assertSee('شرکت نمونه')
            ->assertSee('تعطیلات شرکت')
            ->assertDontSee(route('employer.holidays.index'), false);
    }

    public function test_old_holidays_address_opens_the_organization_profile(): void
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        Organization::factory()->create(['user_id' => $employer->id]);

        $this->actingAs($employer)
            ->get(route('employer.holidays.index'))
            ->assertRedirect(route('employer.organization.profile'));
    }

    public function test_profile_shows_default_voip_and_defined_crm_without_editable_fields(): void
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create([
            'user_id' => $employer->id,
            'title' => 'آواپرداز',
        ]);

        $voipProvider = VoipProvider::query()->create([
            'name' => 'Simotel',
            'code' => VoipProviderCode::Simotel->value,
            'adapter_class' => SimotelVoipAdapter::class,
            'is_active' => true,
        ]);

        $defaultConnection = OrganizationVoipConnection::query()->create([
            'organization_id' => $organization->id,
            'voip_provider_id' => $voipProvider->id,
            'name' => 'خط اصلی',
            'credentials' => ['api_key' => 'voip-secret'],
            'is_default' => true,
            'is_active' => true,
        ]);

        OrganizationVoipConnection::query()->create([
            'organization_id' => $organization->id,
            'voip_provider_id' => $voipProvider->id,
            'name' => 'خط پشتیبان',
            'credentials' => [],
            'is_default' => false,
            'is_active' => true,
        ]);

        $crmProvider = CrmProvider::query()->create([
            'name' => 'Didar',
            'code' => CrmProviderCode::Didar->value,
            'is_active' => true,
        ]);

        OrganizationCrmConnection::query()->create([
            'organization_id' => $organization->id,
            'crm_provider_id' => $crmProvider->id,
            'name' => 'دیدار فروش',
            'credentials' => ['api_key' => 'crm-secret-value'],
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->actingAs($employer);

        $html = Livewire::test(Profile::class)
            ->assertSee('آواپرداز')
            ->assertSee('Simotel')
            ->assertSee('خط اصلی')
            ->assertSee($defaultConnection->inbound_webhook_url)
            ->assertDontSee('خط پشتیبان')
            ->assertSee('اطلاعات CRM')
            ->assertSee('دیدار فروش')
            ->assertSee('Didar CRM')
            ->assertDontSee('voip-secret')
            ->assertDontSee('crm-secret-value')
            ->assertDontSee('wire:model="organizationTitle"', false)
            ->html();

        $this->assertDoesNotMatchRegularExpression('/<(input|textarea|select)[^>]*(نام سازمان|organizationTitle)/u', $html);
    }

    public function test_profile_hides_crm_until_a_connection_is_defined(): void
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        Organization::factory()->create(['user_id' => $employer->id]);

        $this->actingAs($employer);

        Livewire::test(Profile::class)
            ->assertSee('تعریف نشده')
            ->assertDontSee('اطلاعات CRM');
    }
}
