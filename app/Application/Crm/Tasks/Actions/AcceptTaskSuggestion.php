<?php

namespace App\Application\Crm\Tasks\Actions;

use App\Application\Crm\Tasks\TaskAuthorization;
use App\Domain\Crm\Tasks\TaskDueBasis;
use App\Domain\Crm\Tasks\TaskSource;
use App\Domain\Crm\Tasks\TaskSuggestionStatus;
use App\Models\CrmAiTaskSuggestion;
use App\Models\CrmTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptTaskSuggestion
{
    public function __construct(
        private TaskAuthorization $auth,
        private CreateTask $create,
    ) {}

    /** @param  array<string, mixed>  $overrides */
    public function handle(CrmAiTaskSuggestion $suggestion, User $actor, array $overrides = []): CrmTask
    {
        $this->auth->assertInternalCrm((int) $suggestion->organization_id);
        $this->auth->authorize($this->auth->canCreate($actor, (int) $suggestion->organization_id));

        return DB::transaction(function () use ($suggestion, $actor, $overrides): CrmTask {
            $locked = CrmAiTaskSuggestion::query()->whereKey($suggestion->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === TaskSuggestionStatus::Accepted && $locked->appliedTask) {
                return $locked->appliedTask;
            }

            if ($locked->status === TaskSuggestionStatus::Dismissed) {
                throw ValidationException::withMessages([
                    'suggestion' => 'پیشنهاد ردشده را نمی‌توان تأیید کرد.',
                ]);
            }

            $title = trim((string) ($overrides['title'] ?? $locked->proposed_title));
            $dueAt = $overrides['due_at'] ?? $locked->proposed_due_at?->toDateTimeString();
            $assignee = $overrides['assignee_organization_user_id'] ?? $this->auth->membershipId($actor, (int) $locked->organization_id);

            $task = $this->create->handle((int) $locked->organization_id, $actor, [
                'title' => $title,
                'description' => $overrides['description'] ?? $locked->proposed_description,
                'customer_id' => $locked->customer_id,
                'crm_deal_id' => $overrides['crm_deal_id'] ?? $locked->crm_deal_id,
                'assignee_organization_user_id' => $assignee,
                'due_at' => $dueAt,
                'due_source' => TaskDueBasis::Confirmed->value,
                'priority' => $overrides['priority'] ?? 'normal',
                'call_id' => $locked->call_id,
                'conversation_analysis_id' => $locked->conversation_analysis_id,
            ], TaskSource::Analysis);

            $locked->update([
                'status' => TaskSuggestionStatus::Accepted,
                'applied_crm_task_id' => $task->id,
                'resolved_by_user_id' => $actor->id,
                'resolved_at' => now(),
            ]);

            return $task;
        });
    }
}
