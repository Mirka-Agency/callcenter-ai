<?php

namespace App\Application\Crm\Tasks\Actions;

use App\Application\Crm\Tasks\TaskAudit;
use App\Application\Crm\Tasks\TaskAuthorization;
use App\Application\Crm\Tasks\TaskTenant;
use App\Domain\Crm\Tasks\TaskDueBasis;
use App\Domain\Crm\Tasks\TaskEventType;
use App\Domain\Crm\Tasks\TaskPriority;
use App\Models\CrmTask;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateTask
{
    public function __construct(
        private TaskAuthorization $auth,
        private TaskTenant $tenant,
        private TaskAudit $audit,
    ) {}

    /** @param  array<string, mixed>  $input */
    public function handle(CrmTask $task, User $actor, array $input): CrmTask
    {
        $this->auth->authorize($this->auth->canUpdate($actor, $task));

        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 180) {
            throw ValidationException::withMessages([
                'title' => 'عنوان وظیفه الزامی است و حداکثر ۱۸۰ نویسه دارد.',
            ]);
        }

        $priority = TaskPriority::tryFrom((string) ($input['priority'] ?? $task->priority->value)) ?? $task->priority;
        $description = trim((string) ($input['description'] ?? ''));
        $description = $description === '' ? null : $description;
        $customerId = $this->nullableInt($input['customer_id'] ?? $task->customer_id);
        $companyId = $this->nullableInt($input['customer_company_id'] ?? $task->customer_company_id);
        $dealId = $this->nullableInt($input['crm_deal_id'] ?? $task->crm_deal_id);
        $dueAt = array_key_exists('due_at', $input)
            ? $this->dueAt($input['due_at'])
            : $task->due_at;

        $organizationId = (int) $task->organization_id;
        $customer = $customerId ? $this->tenant->customer($organizationId, $customerId) : null;
        $company = $companyId ? $this->tenant->company($organizationId, $companyId) : null;
        $deal = $dealId ? $this->tenant->deal($organizationId, $dealId) : null;
        $this->tenant->assertDealCustomerCompatible($deal, $customer);

        return DB::transaction(function () use ($task, $actor, $title, $description, $priority, $customer, $company, $deal, $dueAt, $input): CrmTask {
            $locked = CrmTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            $this->assertVersion($locked, $input['expected_version'] ?? null);

            if (! $locked->status->isOpen()) {
                throw ValidationException::withMessages([
                    'status' => 'وظیفه بسته‌شده را ابتدا بازگشایی کنید.',
                ]);
            }

            $previous = $locked->auditSnapshot();
            $dueChanged = $dueAt?->toDateTimeString() !== $locked->due_at?->toDateTimeString();
            $priorityChanged = $priority !== $locked->priority;

            $locked->fill([
                'title' => $title,
                'description' => $description,
                'priority' => $priority,
                'customer_id' => $customer?->id,
                'customer_company_id' => $company?->id ?? $customer?->customer_company_id,
                'crm_deal_id' => $deal?->id,
                'due_at' => $dueAt,
                'due_source' => $dueChanged ? TaskDueBasis::Human : $locked->due_source,
                'version' => ((int) $locked->version) + 1,
            ]);
            $locked->save();

            $fresh = $locked->fresh() ?? $locked;
            $this->audit->record($fresh, TaskEventType::Updated, $actor->id, 'manual', $previous, $fresh->auditSnapshot());
            if ($dueChanged) {
                $this->audit->record($fresh, TaskEventType::Rescheduled, $actor->id, 'manual', [
                    'due_at' => $previous['due_at'],
                ], ['due_at' => $fresh->due_at?->toDateTimeString()]);
            }
            if ($priorityChanged) {
                $this->audit->record($fresh, TaskEventType::PriorityChanged, $actor->id, 'manual', [
                    'priority' => $previous['priority'],
                ], ['priority' => $fresh->priority->value]);
            }

            return $fresh->load(['assignee', 'customer', 'company', 'deal']);
        });
    }

    private function assertVersion(CrmTask $task, mixed $expected): void
    {
        if ($expected === null || $expected === '') {
            return;
        }
        if (! is_numeric($expected) || (int) $expected !== (int) $task->version) {
            throw ValidationException::withMessages([
                'task' => 'این وظیفه هم‌زمان ویرایش شده است. صفحه را تازه کنید.',
            ]);
        }
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

    private function dueAt(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['due_at' => 'موعد وظیفه معتبر نیست.']);
        }
    }
}
