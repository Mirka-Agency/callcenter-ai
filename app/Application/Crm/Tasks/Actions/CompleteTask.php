<?php

namespace App\Application\Crm\Tasks\Actions;

use App\Application\Crm\Tasks\TaskAudit;
use App\Application\Crm\Tasks\TaskAuthorization;
use App\Application\Crm\Tasks\TaskState;
use App\Domain\Crm\Tasks\Events\TaskCompleted;
use App\Domain\Crm\Tasks\TaskEventType;
use App\Domain\Crm\Tasks\TaskStatus;
use App\Models\CrmTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteTask
{
    public function __construct(
        private TaskAuthorization $auth,
        private TaskAudit $audit,
    ) {}

    public function handle(CrmTask $task, User $actor, ?string $outcome = null, mixed $expectedVersion = null): CrmTask
    {
        $this->auth->authorize($this->auth->canComplete($actor, $task));
        $outcome = trim((string) $outcome);
        $outcome = $outcome === '' ? null : $outcome;
        if ($outcome !== null && mb_strlen($outcome) > 500) {
            throw ValidationException::withMessages([
                'completion_outcome' => 'نتیجه انجام حداکثر ۵۰۰ نویسه دارد.',
            ]);
        }

        return DB::transaction(function () use ($task, $actor, $outcome, $expectedVersion): CrmTask {
            $locked = CrmTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            if ($expectedVersion !== null && $expectedVersion !== '' && (int) $expectedVersion !== (int) $locked->version) {
                throw ValidationException::withMessages([
                    'task' => 'این وظیفه هم‌زمان ویرایش شده است. صفحه را تازه کنید.',
                ]);
            }

            if ($locked->status === TaskStatus::Completed) {
                return $locked;
            }

            TaskState::assertCanComplete($locked);
            TaskState::assertTransition($locked, TaskStatus::Completed);
            $previous = $locked->auditSnapshot();
            $locked->fill([
                'status' => TaskStatus::Completed,
                'completed_at' => now(),
                'completed_by_user_id' => $actor->id,
                'completion_outcome' => $outcome,
                'version' => ((int) $locked->version) + 1,
            ])->save();
            $fresh = $locked->fresh() ?? $locked;
            $this->audit->record($fresh, TaskEventType::Completed, $actor->id, 'manual', $previous, $fresh->auditSnapshot());
            $this->audit->afterCommit(new TaskCompleted((int) $fresh->organization_id, (int) $fresh->id, $actor->id, 'manual'));

            return $fresh;
        });
    }
}
