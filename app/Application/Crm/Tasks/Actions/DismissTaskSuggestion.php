<?php

namespace App\Application\Crm\Tasks\Actions;

use App\Application\Crm\Tasks\TaskAuthorization;
use App\Domain\Crm\Tasks\TaskSuggestionStatus;
use App\Models\CrmAiTaskSuggestion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DismissTaskSuggestion
{
    public function __construct(private TaskAuthorization $auth) {}

    public function handle(CrmAiTaskSuggestion $suggestion, User $actor): CrmAiTaskSuggestion
    {
        $this->auth->assertInternalCrm((int) $suggestion->organization_id);
        $this->auth->authorize($this->auth->canCreate($actor, (int) $suggestion->organization_id));

        return DB::transaction(function () use ($suggestion, $actor): CrmAiTaskSuggestion {
            $locked = CrmAiTaskSuggestion::query()->whereKey($suggestion->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === TaskSuggestionStatus::Accepted) {
                throw ValidationException::withMessages([
                    'suggestion' => 'پیشنهاد تأییدشده را نمی‌توان رد کرد.',
                ]);
            }

            if ($locked->status === TaskSuggestionStatus::Dismissed) {
                return $locked;
            }

            $locked->update([
                'status' => TaskSuggestionStatus::Dismissed,
                'resolved_by_user_id' => $actor->id,
                'resolved_at' => now(),
            ]);

            return $locked->fresh() ?? $locked;
        });
    }
}
