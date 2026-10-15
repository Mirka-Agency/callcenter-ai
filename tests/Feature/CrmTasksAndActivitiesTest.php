<?php

namespace Tests\Feature;

use App\Application\Crm\Deals\Actions\CreateDeal;
use App\Application\Crm\Deals\Actions\EnsureDefaultPipeline;
use App\Application\Crm\Tasks\Actions\AcceptTaskSuggestion;
use App\Application\Crm\Tasks\Actions\AssignTask;
use App\Application\Crm\Tasks\Actions\CancelTask;
use App\Application\Crm\Tasks\Actions\CompleteTask;
use App\Application\Crm\Tasks\Actions\CreateTask;
use App\Application\Crm\Tasks\Actions\DismissTaskSuggestion;
use App\Application\Crm\Tasks\Actions\ReopenTask;
use App\Application\Crm\Tasks\Actions\SuggestTasksFromAnalysis;
use App\Application\Crm\Tasks\Actions\UpdateTask;
use App\Console\Commands\SendCrmTaskRemindersCommand;
use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Crm\OrganizationCrmMode;
use App\Domain\Crm\Tasks\TaskSource;
use App\Domain\Crm\Tasks\TaskStatus;
use App\Domain\Crm\Tasks\TaskSuggestionStatus;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Enums\UserRole;
use App\Models\Call;
use App\Models\CallRecording;
use App\Models\ConversationAnalysis;
use App\Models\CrmAiTaskSuggestion;
use App\Models\CrmTask;
use App\Models\CrmTaskEvent;
use App\Models\CrmTaskReminder;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\CustomerProfileUpdateService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CrmTasksAndActivitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_assign_complete_and_reopen_task(): void
    {
        [$organization, $manager, $agent] = $this->organization();
        $customer = $this->customer($organization, '09123330001');

        $task = app(CreateTask::class)->handle($organization->id, $manager, [
            'title' => 'تماس پیگیری پیشنهاد',
            'customer_id' => $customer->id,
            'assignee_organization_user_id' => $agent->id,
            'due_at' => now()->addDay()->toDateTimeString(),
            'priority' => 'high',
        ]);

        $this->assertSame(TaskStatus::Pending, $task->status);
        $this->assertDatabaseHas('crm_task_events', ['crm_task_id' => $task->id, 'type' => 'created']);

        $assigned = app(AssignTask::class)->handle($task, $manager, $agent->id, $task->version);
        $this->assertSame($agent->id, (int) $assigned->assignee_organization_user_id);

        $completed = app(CompleteTask::class)->handle($assigned, $manager, 'انجام شد', $assigned->version);
        $this->assertSame(TaskStatus::Completed, $completed->status);
        $this->assertNotNull($completed->completed_at);
        $this->assertSame(1, CrmTaskEvent::query()->where('crm_task_id', $task->id)->where('type', 'completed')->count());

        $again = app(CompleteTask::class)->handle($completed, $manager, 'دوباره');
        $this->assertSame($completed->id, $again->id);
        $this->assertSame(1, CrmTaskEvent::query()->where('crm_task_id', $task->id)->where('type', 'completed')->count());

        $reopened = app(ReopenTask::class)->handle($completed, $manager);
        $this->assertSame(TaskStatus::Pending, $reopened->status);
        $this->assertNull($reopened->completed_at);
        $this->assertDatabaseHas('crm_task_events', ['crm_task_id' => $task->id, 'type' => 'reopened']);
    }

    public function test_task_links_customer_deal_and_rejects_cross_tenant(): void
    {
        [$organization, $manager] = $this->organization();
        $other = Organization::factory()->create(['crm_mode' => OrganizationCrmMode::Internal]);
        $pipeline = app(EnsureDefaultPipeline::class)->handle($organization->id);
        $customer = $this->customer($organization, '09123330002');
        $foreign = $this->customer($other, '09123330003');
        $deal = app(CreateDeal::class)->handle($organization->id, $manager, [
            'title' => 'معامله مرتبط',
            'pipeline_id' => $pipeline->id,
            'stage_id' => $pipeline->stages->first()->id,
            'customer_ids' => [$customer->id],
            'primary_customer_id' => $customer->id,
        ]);

        $task = app(CreateTask::class)->handle($organization->id, $manager, [
            'title' => 'هماهنگی جلسه',
            'customer_id' => $customer->id,
            'crm_deal_id' => $deal->id,
        ]);
        $this->assertSame($deal->id, (int) $task->crm_deal_id);

        $failed = false;
        try {
            app(CreateTask::class)->handle($organization->id, $manager, [
                'title' => 'نامعتبر',
                'customer_id' => $foreign->id,
            ]);
        } catch (ValidationException) {
            $failed = true;
        }
        $this->assertTrue($failed);
    }

    public function test_cancelled_task_cannot_complete_without_reopen(): void
    {
        [$organization, $manager] = $this->organization();
        $task = app(CreateTask::class)->handle($organization->id, $manager, [
            'title' => 'پیگیری لغو',
        ]);
        $cancelled = app(CancelTask::class)->handle($task, $manager, 'نیازی نیست');
        $this->assertSame(TaskStatus::Cancelled, $cancelled->status);

        $blocked = false;
        try {
            app(CompleteTask::class)->handle($cancelled, $manager);
        } catch (ValidationException) {
            $blocked = true;
        }
        $this->assertTrue($blocked);
    }

    public function test_analysis_suggests_tasks_idempotently_and_accept_creates_task(): void
    {
        [$organization, $manager, $agent] = $this->organization();
        $customer = $this->customer($organization, '09123330004');
        $analysis = $this->analysis($organization, $agent, $customer, '09123330004', [
            'next_actions' => ['ارسال پیشنهاد کتبی', 'هماهنگی دمو'],
        ]);

        $first = app(SuggestTasksFromAnalysis::class)->handle($analysis, $customer->id);
        $second = app(SuggestTasksFromAnalysis::class)->handle($analysis, $customer->id);
        $this->assertSame(2, $first);
        $this->assertSame(0, $second);
        $this->assertSame(2, CrmAiTaskSuggestion::query()->count());

        $suggestion = CrmAiTaskSuggestion::query()->first();
        $task = app(AcceptTaskSuggestion::class)->handle($suggestion, $manager);
        $this->assertSame(TaskSource::Analysis, $task->source);
        $this->assertSame(TaskSuggestionStatus::Accepted, $suggestion->fresh()->status);

        app(DismissTaskSuggestion::class)->handle(CrmAiTaskSuggestion::query()->orderByDesc('id')->first(), $manager);
        $dismissed = CrmAiTaskSuggestion::query()->orderByDesc('id')->first();
        $this->assertSame(TaskSuggestionStatus::Dismissed, $dismissed->status);

        $again = app(SuggestTasksFromAnalysis::class)->handle($analysis, $customer->id);
        $this->assertSame(0, $again);
        $this->assertSame(TaskSuggestionStatus::Dismissed, $dismissed->fresh()->status);
    }

    public function test_personal_calls_and_external_mode_skip_task_suggestions(): void
    {
        [$organization, $manager, $agent] = $this->organization();
        $customer = $this->customer($organization, '09123330005');
        $personal = $this->analysis($organization, $agent, $customer, '09123330005', [
            'next_actions' => ['تماس بعدی'],
            'personal' => true,
        ]);
        $this->assertSame(0, app(SuggestTasksFromAnalysis::class)->handle($personal, $customer->id));

        $organization->update(['crm_mode' => OrganizationCrmMode::External]);
        $analysis = $this->analysis($organization, $agent, $customer, '09123330005', [
            'next_actions' => ['تماس بعدی'],
            'suffix' => 'ext',
        ]);
        $this->assertSame(0, app(SuggestTasksFromAnalysis::class)->handle($analysis, $customer->id));

        $blocked = false;
        try {
            app(CreateTask::class)->handle($organization->id, $manager, ['title' => 'نباید ساخته شود']);
        } catch (AuthorizationException) {
            $blocked = true;
        }
        $this->assertTrue($blocked);
    }

    public function test_reminders_are_idempotent_and_skip_completed_tasks(): void
    {
        [$organization, $manager, $agent] = $this->organization();
        $task = app(CreateTask::class)->handle($organization->id, $manager, [
            'title' => 'یادآوری',
            'assignee_organization_user_id' => $agent->id,
            'due_at' => now()->subHour()->toDateTimeString(),
        ]);

        Artisan::call(SendCrmTaskRemindersCommand::class);
        Artisan::call(SendCrmTaskRemindersCommand::class);
        $this->assertSame(1, CrmTaskReminder::query()->where('crm_task_id', $task->id)->count());

        app(CompleteTask::class)->handle($task->fresh(), $manager, null, $task->fresh()->version);
        Artisan::call(SendCrmTaskRemindersCommand::class);
        $this->assertSame(1, CrmTaskReminder::query()->where('crm_task_id', $task->id)->count());
    }

    public function test_human_due_date_is_preserved_on_update_and_concurrency_conflicts(): void
    {
        [$organization, $manager] = $this->organization();
        $task = app(CreateTask::class)->handle($organization->id, $manager, [
            'title' => 'موعد انسانی',
            'due_at' => now()->addDays(2)->toDateTimeString(),
        ]);

        $updated = app(UpdateTask::class)->handle($task, $manager, [
            'title' => 'موعد انسانی',
            'due_at' => now()->addDays(5)->toDateTimeString(),
            'expected_version' => $task->version,
        ]);
        $this->assertSame('human', $updated->due_source?->value);

        $conflict = false;
        try {
            app(UpdateTask::class)->handle($updated, $manager, [
                'title' => 'تداخل',
                'expected_version' => $task->version,
            ]);
        } catch (ValidationException) {
            $conflict = true;
        }
        $this->assertTrue($conflict);
    }

    public function test_tasks_pages_render_for_internal_crm(): void
    {
        [$organization, $manager] = $this->organization();
        app(CreateTask::class)->handle($organization->id, $manager, ['title' => 'نمایش صفحه']);

        $this->actingAs($manager)
            ->get(route('employer.crm.tasks.index'))
            ->assertOk()
            ->assertSee('وظایف و پیگیری‌ها', false)
            ->assertSee('نمایش صفحه', false);
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

    /** @param  array{next_actions?: list<string>, personal?: bool, suffix?: string}  $extra */
    private function analysis(Organization $organization, OrganizationUser $agent, Customer $customer, string $phone, array $extra = []): ConversationAnalysis
    {
        $suffix = $extra['suffix'] ?? 'one';
        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $agent->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'novatel',
            'external_call_id' => 'task-'.$suffix.'-'.$phone,
            'direction' => 'inbound',
            'caller_number' => $phone,
            'receiver_number' => '02100000000',
            'status' => 'completed',
            'processing_status' => 'analyzed',
            'started_at' => now()->subHour(),
            'customer_id' => $customer->id,
        ]);
        CallRecording::query()->create([
            'call_id' => $call->id,
            'source_url' => 'https://pbx.example/'.$call->id.'.wav',
            'storage_disk' => 'local',
            'storage_path' => 'recordings/test-'.$call->id.'.wav',
            'status' => 'completed',
            'is_expired' => false,
        ]);

        return ConversationAnalysis::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $agent->id,
            'call_id' => $call->id,
            'source' => ConversationSource::Voip,
            'llm_provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'score' => 80,
            'summary' => 'مشتری درخواست پیگیری کرد.',
            'sentiment' => AnalysisSentiment::Neutral,
            'strengths_json' => [],
            'weaknesses_json' => [],
            'next_actions_json' => $extra['next_actions'] ?? [],
            'customer_identity_json' => [],
            'lead_quality_json' => ['score' => 75],
            'analyzed_at' => now(),
            'is_personal' => (bool) ($extra['personal'] ?? false),
        ]);
    }
}
