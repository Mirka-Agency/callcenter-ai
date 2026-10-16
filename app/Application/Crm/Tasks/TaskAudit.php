<?php

namespace App\Application\Crm\Tasks;

use App\Domain\Crm\Tasks\TaskEventType;
use App\Models\CrmTask;
use App\Models\CrmTaskEvent;
use Illuminate\Support\Facades\DB;

class TaskAudit
{
    /**
     * @param  array<string, mixed>|null  $previous
     * @param  array<string, mixed>|null  $next
     */
    public function record(
        CrmTask $task,
        TaskEventType $type,
        ?int $actorUserId,
        string $source,
        ?array $previous = null,
        ?array $next = null,
        ?string $summary = null,
    ): void {
        CrmTaskEvent::query()->create([
            'organization_id' => $task->organization_id,
            'crm_task_id' => $task->id,
            'type' => $type,
            'actor_user_id' => $actorUserId,
            'source' => $source,
            'previous_json' => $previous,
            'next_json' => $next,
            'summary' => $summary,
        ]);
    }

    public function afterCommit(object $event): void
    {
        DB::afterCommit(static function () use ($event): void {
            event($event);
        });
    }
}
