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

class ReopenTask
{
    public function __construct(
        private TaskAuthorization $auth,
        private TaskAudit $audit,
    ) {}

    public function handle(CrmTask $task, User $actor): CrmTask
    {
        $this->auth->authorize($this->auth->canReopen($actor, $task));

        return DB::transaction(function () use ($task, $actor): CrmTask {
            $locked = CrmTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            if ($locked->status->isOpen()) {
                return $locked;
            }
            TaskState::assertCanReopen($locked);
            TaskState::assertTransition($locked, TaskStatus::Pending);
            $previous = $locked->auditSnapshot();
            $locked->fill([
                'status' => TaskStatus::Pending,
                'completed_at' => null,
                'completed_by_user_id' => null,
                'completion_outcome' => null,
                'version' => ((int) $locked->version) + 1,
            ])->save();
            $fresh = $locked->fresh() ?? $locked;
            $this->audit->record($fresh, TaskEventType::Reopened, $actor->id, 'manual', $previous, $fresh->auditSnapshot());

            return $fresh;
        });
    }
}
