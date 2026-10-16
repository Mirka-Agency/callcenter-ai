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

class StartTask
{
    public function __construct(
        private TaskAuthorization $auth,
        private TaskAudit $audit,
    ) {}

    public function handle(CrmTask $task, User $actor): CrmTask
    {
        $this->auth->authorize($this->auth->canUpdate($actor, $task));

        return DB::transaction(function () use ($task, $actor): CrmTask {
            $locked = CrmTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === TaskStatus::InProgress) {
                return $locked;
            }
            TaskState::assertTransition($locked, TaskStatus::InProgress);
            $previous = $locked->auditSnapshot();
            $locked->fill([
                'status' => TaskStatus::InProgress,
                'version' => ((int) $locked->version) + 1,
            ])->save();
            $fresh = $locked->fresh() ?? $locked;
            $this->audit->record($fresh, TaskEventType::Started, $actor->id, 'manual', $previous, $fresh->auditSnapshot());

            return $fresh;
        });
    }
}
