<?php

namespace App\Livewire\Crm\Tasks;

use App\Application\Crm\Tasks\Actions\AcceptTaskSuggestion;
use App\Application\Crm\Tasks\Actions\CompleteTask;
use App\Application\Crm\Tasks\Actions\CreateTask;
use App\Application\Crm\Tasks\Actions\DismissTaskSuggestion;
use App\Application\Crm\Tasks\TaskAuthorization;
use App\Application\Crm\Tasks\TaskOverview;
use App\Application\Crm\Tasks\TaskQuery;
use App\Domain\Crm\Tasks\TaskPriority;
use App\Domain\Crm\Tasks\TaskSource;
use App\Domain\Crm\Tasks\TaskSuggestionStatus;
use App\Enums\UserRole;
use App\Livewire\Crm\Concerns\ResolvesCrmContext;
use App\Models\CrmAiTaskSuggestion;
use App\Models\CrmDeal;
use App\Models\Customer;
use App\Models\OrganizationUser;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('وظایف و پیگیری‌ها')]
class Index extends Component
{
    use ResolvesCrmContext;
    use WithPagination;

    #[Url(except: 'open')]
    public string $status = 'open';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'all')]
    public string $assignee = 'all';

    #[Url(except: '')]
    public string $priority = '';

    #[Url(except: 'due')]
    public string $sort = 'due';

    #[Url(as: 'customer', except: '')]
    public string $customerFilter = '';

    #[Url(as: 'deal', except: '')]
    public string $dealFilter = '';

    public bool $showCreate = false;

    public string $title = '';

    public string $description = '';

    public string $priorityInput = 'normal';

    public string $assigneeId = '';

    public string $customerId = '';

    public string $customerQuery = '';

    public string $dealId = '';

    public string $dueAt = '';

    public function mount(): void
    {
        if ($this->dealFilter !== '' && $this->dealId === '') {
            $this->dealId = $this->dealFilter;
        }
        if ($this->customerFilter !== '' && $this->customerId === '') {
            $this->customerId = $this->customerFilter;
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function createTask(CreateTask $action): void
    {
        $task = $action->handle($this->crmOrganizationId(), $this->crmUser(), [
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priorityInput,
            'assignee_organization_user_id' => $this->assigneeId !== '' ? (int) $this->assigneeId : null,
            'customer_id' => $this->customerId !== '' ? (int) $this->customerId : null,
            'crm_deal_id' => $this->dealId !== '' ? (int) $this->dealId : null,
            'due_at' => $this->dueAt !== '' ? $this->dueAt : null,
        ], TaskSource::Manual);

        $this->reset(['showCreate', 'title', 'description', 'assigneeId', 'customerId', 'customerQuery', 'dealId', 'dueAt']);
        $this->priorityInput = 'normal';
        $this->toast('وظیفه ثبت شد.');
        $this->redirect(route($this->crmPortal().'.crm.tasks.show', $task), navigate: true);
    }

    public function complete(int $taskId, CompleteTask $action, TaskQuery $query): void
    {
        $task = $query->findVisible($this->crmOrganizationId(), $this->crmUser(), $taskId);
        $action->handle($task, $this->crmUser());
        $this->toast('وظیفه تکمیل شد.');
    }

    public function acceptSuggestion(int $suggestionId, AcceptTaskSuggestion $action): void
    {
        $suggestion = CrmAiTaskSuggestion::query()
            ->where('organization_id', $this->crmOrganizationId())
            ->where('status', TaskSuggestionStatus::Pending)
            ->findOrFail($suggestionId);
        $task = $action->handle($suggestion, $this->crmUser());
        $this->toast('پیشنهاد به وظیفه تبدیل شد.');
        $this->redirect(route($this->crmPortal().'.crm.tasks.show', $task), navigate: true);
    }

    public function dismissSuggestion(int $suggestionId, DismissTaskSuggestion $action): void
    {
        $suggestion = CrmAiTaskSuggestion::query()
            ->where('organization_id', $this->crmOrganizationId())
            ->findOrFail($suggestionId);
        $action->handle($suggestion, $this->crmUser());
        $this->toast('پیشنهاد رد شد.');
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

    public function render(TaskQuery $query, TaskOverview $overview, TaskAuthorization $auth)
    {
        $organizationId = $this->crmOrganizationId();
        $actor = $this->crmUser();
        $filters = [
            'status' => $this->status,
            'search' => $this->search,
            'assignee' => $this->assignee,
            'priority' => $this->priority,
            'sort' => $this->sort,
            'customer_id' => $this->customerFilter !== '' ? (int) $this->customerFilter : null,
            'crm_deal_id' => $this->dealFilter !== '' ? (int) $this->dealFilter : null,
        ];

        return view('livewire.crm.tasks.index', [
            'tasks' => $query->paginate($organizationId, $actor, $filters),
            'summary' => $overview->summarize($organizationId, $actor),
            'owners' => OrganizationUser::query()->where('organization_id', $organizationId)->where('is_active', true)->orderBy('last_name')->get(),
            'deals' => CrmDeal::query()->where('organization_id', $organizationId)->where('status', 'open')->latest('id')->limit(50)->get(),
            'priorities' => TaskPriority::cases(),
            'suggestions' => CrmAiTaskSuggestion::query()
                ->where('organization_id', $organizationId)
                ->where('status', TaskSuggestionStatus::Pending)
                ->with('customer')
                ->latest('id')
                ->limit(10)
                ->get(),
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
}
