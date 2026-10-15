<?php

namespace App\Livewire\Crm\Reminders;

use App\Application\Crm\Tasks\Actions\CancelTask;
use App\Application\Crm\Tasks\Actions\CompleteTask;
use App\Application\Crm\Tasks\Actions\CreateTask;
use App\Application\Crm\Tasks\Actions\UpdateTask;
use App\Application\Crm\Tasks\TaskAuthorization;
use App\Application\Crm\Tasks\TaskOverview;
use App\Application\Crm\Tasks\TaskQuery;
use App\Domain\Crm\Tasks\TaskPriority;
use App\Domain\Crm\Tasks\TaskSource;
use App\Enums\UserRole;
use App\Livewire\Crm\Concerns\ResolvesCrmContext;
use App\Models\CrmDeal;
use App\Models\CrmTask;
use App\Models\Customer;
use App\Models\OrganizationUser;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('یادآوری‌ها')]
class Index extends Component
{
    use ResolvesCrmContext;
    use WithPagination;

    #[Url(except: 'open')]
    public string $status = 'open';

    #[Url(except: 'all')]
    public string $scope = 'all';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $expectedVersion = '';

    public string $title = '';

    public string $description = '';

    public string $priorityInput = 'normal';

    public string $assigneeId = '';

    public string $customerId = '';

    public string $customerQuery = '';

    public string $dealId = '';

    public string $dueAt = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedScope(): void
    {
        $this->resetPage();
    }

    public function setStatus(string $status): void
    {
        $this->status = in_array($status, ['open', 'all'], true) ? $status : 'open';
        $this->resetPage();
    }

    public function setScope(string $scope): void
    {
        $this->scope = in_array($scope, ['all', 'sales'], true) ? $scope : 'all';
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $taskId, TaskQuery $query): void
    {
        $task = $query->findVisible($this->crmOrganizationId(), $this->crmUser(), $taskId);
        if (! $task->status->isOpen()) {
            $this->toast('فقط یادآوری‌های باز قابل ویرایش هستند.', 'error');

            return;
        }

        $this->editingId = (int) $task->id;
        $this->expectedVersion = (string) $task->version;
        $this->title = (string) $task->title;
        $this->description = (string) ($task->description ?? '');
        $this->priorityInput = $task->priority->value;
        $this->assigneeId = $task->assignee_organization_user_id ? (string) $task->assignee_organization_user_id : '';
        $this->customerId = $task->customer_id ? (string) $task->customer_id : '';
        $this->customerQuery = $task->customer?->displayName() ?? '';
        $this->dealId = $task->crm_deal_id ? (string) $task->crm_deal_id : '';
        $this->dueAt = $task->due_at?->format('Y-m-d\\TH:i') ?? '';
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function save(CreateTask $create, UpdateTask $update, TaskQuery $query): void
    {
        $payload = [
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priorityInput,
            'assignee_organization_user_id' => $this->assigneeId !== '' ? (int) $this->assigneeId : null,
            'customer_id' => $this->customerId !== '' ? (int) $this->customerId : null,
            'crm_deal_id' => $this->dealId !== '' ? (int) $this->dealId : null,
            'due_at' => $this->dueAt !== '' ? $this->dueAt : null,
        ];

        if ($this->editingId !== null) {
            $task = $query->findVisible($this->crmOrganizationId(), $this->crmUser(), $this->editingId);
            $update->handle($task, $this->crmUser(), [
                ...$payload,
                'expected_version' => $this->expectedVersion !== '' ? (int) $this->expectedVersion : null,
            ]);
            $this->toast('یادآوری به‌روزرسانی شد.');
        } else {
            $create->handle($this->crmOrganizationId(), $this->crmUser(), $payload, TaskSource::Manual);
            $this->toast('یادآوری ثبت شد.');
        }

        $this->closeForm();
    }

    public function complete(int $taskId, CompleteTask $action, TaskQuery $query): void
    {
        $task = $query->findVisible($this->crmOrganizationId(), $this->crmUser(), $taskId);
        $action->handle($task, $this->crmUser());
        $this->toast('یادآوری انجام شد.');
    }

    public function remove(int $taskId, CancelTask $action, TaskQuery $query): void
    {
        $task = $query->findVisible($this->crmOrganizationId(), $this->crmUser(), $taskId);
        $action->handle($task, $this->crmUser(), 'حذف از فهرست یادآوری‌ها');
        $this->toast('یادآوری حذف شد.');
    }

    public function chooseCustomer(int $customerId): void
    {
        $customer = Customer::query()->where('organization_id', $this->crmOrganizationId())->find($customerId);
        if (! $customer) {
            return;
        }
        $this->customerId = (string) $customer->id;
        $this->customerQuery = $customer->displayName();
    }

    public function sourceBadge(CrmTask $task): string
    {
        return match ($task->source) {
            TaskSource::Analysis => 'پس از تماس',
            TaskSource::System => 'سامانه',
            default => 'دستی',
        };
    }

    public function render(TaskQuery $query, TaskOverview $overview, TaskAuthorization $auth)
    {
        $organizationId = $this->crmOrganizationId();
        $actor = $this->crmUser();
        $filters = [
            'status' => $this->status === 'all' ? 'all' : 'open',
            'has_deal' => $this->scope === 'sales',
            'sort' => 'due',
        ];

        return view('livewire.crm.reminders.index', [
            'reminders' => $query->paginate($organizationId, $actor, $filters),
            'summary' => $overview->summarize($organizationId, $actor),
            'owners' => OrganizationUser::query()
                ->where('organization_id', $organizationId)
                ->where('is_active', true)
                ->orderBy('last_name')
                ->get(),
            'deals' => CrmDeal::query()
                ->where('organization_id', $organizationId)
                ->where('status', 'open')
                ->latest('id')
                ->limit(50)
                ->get(),
            'priorities' => TaskPriority::cases(),
            'customerChoices' => mb_strlen(trim($this->customerQuery)) >= 2
                ? Customer::query()->forOrganization($organizationId)
                    ->where(function ($inner): void {
                        $term = '%'.addcslashes(trim($this->customerQuery), '%_\\').'%';
                        $inner->where('name', 'like', $term)->orWhere('phone_number', 'like', $term);
                    })->limit(8)->get()
                : collect(),
            'canCreate' => $auth->canCreate($actor, $organizationId),
            'isManager' => $actor->role === UserRole::Employer,
            'portal' => $this->crmPortal(),
        ])->layout($this->crmLayout());
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId',
            'expectedVersion',
            'title',
            'description',
            'assigneeId',
            'customerId',
            'customerQuery',
            'dealId',
            'dueAt',
        ]);
        $this->priorityInput = 'normal';
    }
}
