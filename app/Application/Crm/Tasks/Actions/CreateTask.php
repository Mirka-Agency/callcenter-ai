<?php

namespace App\Application\Crm\Tasks\Actions;

use App\Application\Crm\Tasks\TaskAudit;
use App\Application\Crm\Tasks\TaskAuthorization;
use App\Application\Crm\Tasks\TaskTenant;
use App\Domain\Crm\Tasks\Events\TaskCreated;
use App\Domain\Crm\Tasks\TaskDueBasis;
use App\Domain\Crm\Tasks\TaskEventType;
use App\Domain\Crm\Tasks\TaskPriority;
use App\Domain\Crm\Tasks\TaskSource;
use App\Domain\Crm\Tasks\TaskStatus;
use App\Enums\UserRole;
use App\Models\CrmTask;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateTask
{
    public function __construct(
        private TaskAuthorization $auth,
        private TaskTenant $tenant,
        private TaskAudit $audit,
    ) {}

    /** @param  array<string, mixed>  $input */
    public function handle(int $organizationId, ?User $actor, array $input, TaskSource $source = TaskSource::Manual, bool $asSystem = false): CrmTask
    {
        $this->auth->assertInternalCrm($organizationId);

        if (! $asSystem) {
            if (! $actor instanceof User) {
                throw new AuthorizationException('مجوز این عملیات را ندارید.');
            }
            $this->auth->authorize($this->auth->canCreate($actor, $organizationId));
        }

        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 180) {
            throw ValidationException::withMessages([
                'title' => 'عنوان وظیفه الزامی است و حداکثر ۱۸۰ نویسه دارد.',
            ]);
        }

        $priority = TaskPriority::tryFrom((string) ($input['priority'] ?? TaskPriority::Normal->value)) ?? TaskPriority::Normal;
        $customerId = $this->nullableInt($input['customer_id'] ?? null);
        $companyId = $this->nullableInt($input['customer_company_id'] ?? null);
        $dealId = $this->nullableInt($input['crm_deal_id'] ?? null);
        $assigneeId = $this->nullableInt($input['assignee_organization_user_id'] ?? null);
        $description = $this->text($input['description'] ?? null, 'description', 5000);
        $dueAt = $this->dueAt($input['due_at'] ?? null);
        $dueSource = TaskDueBasis::tryFrom((string) ($input['due_source'] ?? TaskDueBasis::Human->value)) ?? TaskDueBasis::Human;

        $customer = $customerId ? $this->tenant->customer($organizationId, $customerId) : null;
        $company = $companyId ? $this->tenant->company($organizationId, $companyId) : null;
        $deal = $dealId ? $this->tenant->deal($organizationId, $dealId) : null;
        if ($assigneeId) {
            $this->tenant->assignee($organizationId, $assigneeId);
        }
        $this->tenant->assertDealCustomerCompatible($deal, $customer);

        if (! $asSystem && $actor instanceof User && $actor->role === UserRole::Employee) {
            $mine = $this->auth->membershipId($actor, $organizationId);
            if ($assigneeId === null) {
                $assigneeId = $mine;
            } elseif ($assigneeId !== $mine && ! $this->auth->isManager($actor)) {
                throw ValidationException::withMessages([
                    'assignee' => 'کارشناس فقط می‌تواند وظیفه را به خودش تخصیص دهد.',
                ]);
            }
        }

        return DB::transaction(function () use ($organizationId, $actor, $title, $description, $priority, $source, $assigneeId, $customer, $company, $deal, $dueAt, $dueSource, $input): CrmTask {
            $task = CrmTask::query()->create([
                'organization_id' => $organizationId,
                'title' => $title,
                'description' => $description,
                'status' => TaskStatus::Pending,
                'priority' => $priority,
                'source' => $source,
                'assignee_organization_user_id' => $assigneeId,
                'created_by_user_id' => $actor?->id,
                'customer_id' => $customer?->id,
                'customer_company_id' => $company?->id ?? $customer?->customer_company_id,
                'crm_deal_id' => $deal?->id,
                'call_id' => $this->nullableInt($input['call_id'] ?? null),
                'conversation_analysis_id' => $this->nullableInt($input['conversation_analysis_id'] ?? null),
                'due_at' => $dueAt,
                'due_source' => $dueAt ? $dueSource : null,
                'version' => 1,
            ]);

            $this->audit->record($task, TaskEventType::Created, $actor?->id, $source->value, null, $task->auditSnapshot());
            $this->audit->afterCommit(new TaskCreated($organizationId, (int) $task->id, $actor?->id, $source->value));

            return $task->fresh(['assignee', 'customer', 'company', 'deal']) ?? $task;
        });
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value) || (int) $value <= 0) {
            throw ValidationException::withMessages(['task' => 'شناسه انتخاب‌شده معتبر نیست.']);
        }

        return (int) $value;
    }

    private function text(mixed $value, string $field, int $max): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        if (mb_strlen($text) > $max) {
            throw ValidationException::withMessages([$field => 'متن بیش از حد مجاز است.']);
        }

        return $text;
    }

    private function dueAt(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'due_at' => 'موعد وظیفه معتبر نیست.',
            ]);
        }
    }
}
