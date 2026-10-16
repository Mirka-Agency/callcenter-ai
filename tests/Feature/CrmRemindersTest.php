<?php

namespace Tests\Feature;

use App\Application\Crm\Deals\Actions\CreateDeal;
use App\Application\Crm\Deals\Actions\EnsureDefaultPipeline;
use App\Application\Crm\Tasks\Actions\CreateTask;
use App\Domain\Crm\OrganizationCrmMode;
use App\Domain\Crm\Tasks\TaskStatus;
use App\Enums\UserRole;
use App\Livewire\Crm\Reminders\Index as RemindersIndex;
use App\Models\CrmTask;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\CustomerProfileUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CrmRemindersTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminders_page_lists_open_tasks_as_reminders(): void
    {
        [$organization, $manager] = $this->organization();
        $customer = $this->customer($organization, '09124440001');

        app(CreateTask::class)->handle($organization->id, $manager, [
            'title' => 'پیگیری تماس',
            'description' => 'فردا دوباره تماس بگیر',
            'customer_id' => $customer->id,
            'due_at' => now()->subHour()->toDateTimeString(),
        ]);

        $this->actingAs($manager)
            ->get(route('employer.crm.reminders.index'))
            ->assertOk()
            ->assertSee('یادآوری‌ها', false)
            ->assertSee('پیگیری تماس', false)
            ->assertSee('عقب‌افتاده', false)
            ->assertSee($customer->displayName(), false);
    }

    public function test_can_create_complete_and_remove_reminder_from_livewire(): void
    {
        [$organization, $manager] = $this->organization();
        $customer = $this->customer($organization, '09124440002');

        Livewire::actingAs($manager)
            ->test(RemindersIndex::class)
            ->call('openCreate')
            ->set('title', 'یادآوری تست')
            ->set('description', 'توضیح کوتاه')
            ->set('customerId', (string) $customer->id)
            ->set('dueAt', now()->addDay()->format('Y-m-d\\TH:i'))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('یادآوری تست', false);

        $task = CrmTask::query()->where('organization_id', $organization->id)->where('title', 'یادآوری تست')->first();
        $this->assertNotNull($task);
        $this->assertSame(TaskStatus::Pending, $task->status);

        Livewire::actingAs($manager)
            ->test(RemindersIndex::class)
            ->call('complete', $task->id)
            ->assertHasNoErrors();

        $this->assertSame(TaskStatus::Completed, $task->fresh()->status);

        $open = app(CreateTask::class)->handle($organization->id, $manager, [
            'title' => 'برای حذف',
            'due_at' => now()->addDay()->toDateTimeString(),
        ]);

        Livewire::actingAs($manager)
            ->test(RemindersIndex::class)
            ->call('remove', $open->id)
            ->assertHasNoErrors();

        $this->assertSame(TaskStatus::Cancelled, $open->fresh()->status);
    }

    public function test_sales_scope_filters_reminders_linked_to_deals(): void
    {
        [$organization, $manager] = $this->organization();
        $customer = $this->customer($organization, '09124440003');
        $pipeline = app(EnsureDefaultPipeline::class)->handle($organization->id);
        $deal = app(CreateDeal::class)->handle($organization->id, $manager, [
            'title' => 'فرصت فروش',
            'pipeline_id' => $pipeline->id,
            'stage_id' => $pipeline->stages->first()->id,
            'customer_ids' => [$customer->id],
            'primary_customer_id' => $customer->id,
        ]);

        app(CreateTask::class)->handle($organization->id, $manager, [
            'title' => 'بدون فرصت',
        ]);
        app(CreateTask::class)->handle($organization->id, $manager, [
            'title' => 'با فرصت',
            'customer_id' => $customer->id,
            'crm_deal_id' => $deal->id,
        ]);

        Livewire::actingAs($manager)
            ->test(RemindersIndex::class)
            ->call('setScope', 'sales')
            ->assertSee('با فرصت', false)
            ->assertDontSee('بدون فرصت', false);
    }

    public function test_external_crm_blocks_reminders_route(): void
    {
        [$organization, $manager] = $this->organization();
        $organization->update(['crm_mode' => OrganizationCrmMode::External]);

        $this->actingAs($manager)
            ->get(route('employer.crm.reminders.index'))
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

    private function customer(Organization $organization, string $phone): Customer
    {
        return app(CustomerProfileUpdateService::class)->create($organization->id, [
            'phone_number' => $phone,
            'name' => 'شخص '.$phone,
        ]);
    }
}
