<?php

namespace App\Livewire\Crm\Tasks;

use App\Application\Crm\Tasks\Actions\AssignTask;
use App\Application\Crm\Tasks\Actions\CancelTask;
use App\Application\Crm\Tasks\Actions\CompleteTask;
use App\Application\Crm\Tasks\Actions\ReopenTask;
use App\Application\Crm\Tasks\Actions\StartTask;
use App\Application\Crm\Tasks\Actions\UpdateTask;
use App\Application\Crm\Tasks\TaskAuthorization;
use App\Application\Crm\Tasks\TaskQuery;
use App\Domain\Crm\Tasks\TaskPriority;
use App\Enums\UserRole;
use App\Livewire\Crm\Concerns\ResolvesCrmContext;
use App\Models\CrmDeal;
use App\Models\CrmTask;
use App\Models\Customer;
use App\Models\OrganizationUser;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('جزئیات وظیفه')]
class Show extends Component
{
    use ResolvesCrmContext;

    public int $taskId;

    public string $title = '';

    public string $description = '';

    public string $priority = 'normal';

    public string $assigneeId = '';

    public string $customerId = '';

    public string $dealId = '';

    public string $dueAt = '';

    public string $outcome = '';

    public string $expectedVersion = '';

    public function mount(int $task): void
    {
        $this->taskId = $task;
        $this->syncForm($this->loadTask());
    }

    public function save(UpdateTask $action): void
    {
        $updated = $action->handle($this->loadTask(), $this->crmUser(), [
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'customer_id' => $this->customerId !== '' ? (int) $this->customerId : null,
            'crm_deal_id' => $this->dealId !== '' ? (int) $this->dealId : null,
            'due_at' => $this->dueAt !== '' ? $this->dueAt : null,
            'expected_version' => $this->expectedVersion,
        ]);
        $this->syncForm($updated);
        $this->toast('وظیفه ذخیره شد.');
    }

    public function assign(AssignTask $action): void
    {
        $updated = $action->handle(
            $this->loadTask(),
            $this->crmUser(),
            $this->assigneeId !== '' ? (int) $this->assigneeId : null,
            $this->expectedVersion,
        );
        $this->syncForm($updated);
        $this->toast('مسئول وظیفه ثبت شد.');
    }

    public function start(StartTask $action): void
    {
        $this->syncForm($action->handle($this->loadTask(), $this->crmUser()));
        $this->toast('وظیفه شروع شد.');
    }

    public function complete(CompleteTask $action): void
    {
        $this->syncForm($action->handle($this->loadTask(), $this->crmUser(), $this->outcome, $this->expectedVersion));
        $this->outcome = '';
        $this->toast('وظیفه تکمیل شد.');
    }

    public function cancel(CancelTask $action): void
    {
        $this->syncForm($action->handle($this->loadTask(), $this->crmUser(), $this->outcome));
        $this->outcome = '';
        $this->toast('وظیفه لغو شد.');
    }

    public function reopen(ReopenTask $action): void
    {
        $this->syncForm($action->handle($this->loadTask(), $this->crmUser()));
        $this->toast('وظیفه بازگشایی شد.');
    }

    public function render(TaskAuthorization $auth)
    {
        $task = $this->loadTask()->load([
            'assignee', 'customer', 'company', 'deal', 'analysis', 'call', 'events.actor',
        ]);
        $actor = $this->crmUser();

        return view('livewire.crm.tasks.show', [
            'task' => $task,
            'owners' => OrganizationUser::query()->where('organization_id', $task->organization_id)->where('is_active', true)->orderBy('last_name')->get(),
            'deals' => CrmDeal::query()->where('organization_id', $task->organization_id)->latest('id')->limit(40)->get(),
            'customers' => Customer::query()->where('organization_id', $task->organization_id)->latest('id')->limit(40)->get(),
            'priorities' => TaskPriority::cases(),
            'canUpdate' => $auth->canUpdate($actor, $task),
            'canAssign' => $auth->canAssign($actor, $task),
            'canReopen' => $auth->canReopen($actor, $task),
            'isManager' => $actor->role === UserRole::Employer,
            'portal' => $this->crmPortal(),
            'backUrl' => route($this->crmPortal().'.crm.tasks.index'),
        ])->layout($this->crmLayout());
    }

    private function loadTask(): CrmTask
    {
        return app(TaskQuery::class)->findVisible($this->crmOrganizationId(), $this->crmUser(), $this->taskId);
    }

    private function syncForm(CrmTask $task): void
    {
        $this->title = $task->title;
        $this->description = (string) $task->description;
        $this->priority = $task->priority->value;
        $this->assigneeId = $task->assignee_organization_user_id ? (string) $task->assignee_organization_user_id : '';
        $this->customerId = $task->customer_id ? (string) $task->customer_id : '';
        $this->dealId = $task->crm_deal_id ? (string) $task->crm_deal_id : '';
        $this->dueAt = $task->due_at?->format('Y-m-d\TH:i') ?? '';
        $this->expectedVersion = (string) $task->version;
    }
}
