<?php

namespace App\Application\Crm\Tasks\Actions;

use App\Application\Crm\Tasks\TaskAudit;
use App\Application\Crm\Tasks\TaskAuthorization;
use App\Application\Crm\Tasks\TaskState;
use App\Application\Crm\Tasks\TaskTenant;
use App\Domain\Crm\Tasks\TaskEventType;
use App\Models\CrmTask;
use App\Models\User;
use App\Notifications\CrmTaskAssignedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignTask
{
    public function __construct(
        private TaskAuthorization $auth,
        private TaskTenant $tenant,
        private TaskAudit $audit,
    ) {}

    public function handle(CrmTask $task, User $actor, ?int $assigneeId, mixed $expectedVersion = null): CrmTask
    {
        $this->auth->authorize($this->auth->canAssign($actor, $task));

        return DB::transaction(function () use ($task, $actor, $assigneeId, $expectedVersion): CrmTask {
            $locked = CrmTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            TaskState::assertOpen($locked);
            if ($expectedVersion !== null && $expectedVersion !== '' && (int) $expectedVersion !== (int) $locked->version) {
                throw ValidationException::withMessages([
                    'task' => 'این وظیفه هم‌زمان ویرایش شده است. صفحه را تازه کنید.',
                ]);
            }

            if ($assigneeId !== null) {
                $this->tenant->assignee((int) $locked->organization_id, $assigneeId);
            }

            $previous = $locked->auditSnapshot();
            if ((int) ($locked->assignee_organization_user_id ?? 0) === (int) ($assigneeId ?? 0)) {
                return $locked;
            }

            $locked->fill([
                'assignee_organization_user_id' => $assigneeId,
                'version' => ((int) $locked->version) + 1,
            ])->save();

            $fresh = $locked->fresh(['assignee']) ?? $locked;
            $this->audit->record($fresh, TaskEventType::Assigned, $actor->id, 'manual', $previous, $fresh->auditSnapshot());

            DB::afterCommit(function () use ($fresh, $actor): void {
                $user = $fresh->assignee?->user;
                if ($user && (int) $user->id !== (int) $actor->id) {
                    $user->notify(new CrmTaskAssignedNotification($fresh));
                }
            });

            return $fresh;
        });
    }
}
