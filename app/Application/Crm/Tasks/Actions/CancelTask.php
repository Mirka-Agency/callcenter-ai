<?php

namespace App\Application\Crm\Tasks\Actions;

use App\Application\Crm\Tasks\TaskAudit;
use App\Application\Crm\Tasks\TaskAuthorization;
use App\Application\Crm\Tasks\TaskState;
use App\Domain\Crm\Tasks\TaskEventType;
use App\Domain\Crm\Tasks\TaskStatus;
use App\Models\CrmTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelTask
{
    public function __construct(
        private TaskAuthorization $auth,
        private TaskAudit $audit,
    ) {}

    public function handle(CrmTask $task, User $actor, ?string $reason = null): CrmTask
    {
        $this->auth->authorize($this->auth->canCancel($actor, $task));
        $reason = trim((string) $reason);
        $reason = $reason === '' ? null : $reason;

        return DB::transaction(function () use ($task, $actor, $reason): CrmTask {
            $locked = CrmTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === TaskStatus::Cancelled) {
                return $locked;
            }
            TaskState::assertCanCancel($locked);
            TaskState::assertTransition($locked, TaskStatus::Cancelled);
            $previous = $locked->auditSnapshot();
            $locked->fill([
                'status' => TaskStatus::Cancelled,
                'completion_outcome' => $reason,
                'completed_at' => now(),
                'completed_by_user_id' => $actor->id,
                'version' => ((int) $locked->version) + 1,
            ])->save();
            // Cancelled uses completed_at as terminal timestamp; reopen clears it.
            $fresh = $locked->fresh() ?? $locked;
            $this->audit->record($fresh, TaskEventType::Cancelled, $actor->id, 'manual', $previous, $fresh->auditSnapshot(), $reason);

            return $fresh;
        });
    }
}
