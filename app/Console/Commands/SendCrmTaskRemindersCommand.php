<?php

namespace App\Console\Commands;

use App\Domain\Crm\OrganizationCrmMode;
use App\Domain\Crm\Tasks\TaskStatus;
use App\Models\CrmTask;
use App\Models\CrmTaskReminder;
use App\Models\Organization;
use App\Notifications\CrmTaskReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class SendCrmTaskRemindersCommand extends Command
{
    protected $signature = 'crm:tasks-send-reminders';

    protected $description = 'Send idempotent in-app reminders for internal CRM tasks';

    public function handle(): int
    {
        $sent = 0;

        Organization::query()
            ->where('crm_mode', OrganizationCrmMode::Internal->value)
            ->orderBy('id')
            ->each(function (Organization $organization) use (&$sent): void {
                // Re-check mode at processing time.
                if (! $organization->fresh()?->usesInternalCrm()) {
                    return;
                }

                $tasks = CrmTask::query()
                    ->where('organization_id', $organization->id)
                    ->whereIn('status', [TaskStatus::Pending->value, TaskStatus::InProgress->value])
                    ->whereNotNull('due_at')
                    ->whereNotNull('assignee_organization_user_id')
                    ->with('assignee.user')
                    ->where(function ($query): void {
                        $query->where('due_at', '<', now())
                            ->orWhereBetween('due_at', [now(), now()->addHours(4)]);
                    })
                    ->limit(200)
                    ->get();

                foreach ($tasks as $task) {
                    $kind = $task->due_at->lt(now()) ? 'overdue' : 'upcoming';
                    $key = $kind.'|'.($task->due_at?->timestamp ?? 0).'|'.$task->version;

                    try {
                        $created = DB::transaction(function () use ($task, $kind, $key): bool {
                            $exists = CrmTaskReminder::query()
                                ->where('crm_task_id', $task->id)
                                ->where('idempotency_key', $key)
                                ->lockForUpdate()
                                ->exists();

                            if ($exists) {
                                return false;
                            }

                            // Fresh status check before side effect.
                            $fresh = CrmTask::query()->whereKey($task->id)->lockForUpdate()->first();
                            if (! $fresh || ! $fresh->status->isOpen()) {
                                return false;
                            }

                            $org = Organization::query()->find($fresh->organization_id);
                            if (! $org?->usesInternalCrm()) {
                                return false;
                            }

                            CrmTaskReminder::query()->create([
                                'organization_id' => $fresh->organization_id,
                                'crm_task_id' => $fresh->id,
                                'kind' => $kind,
                                'idempotency_key' => $key,
                                'due_at_snapshot' => $fresh->due_at,
                                'sent_at' => now(),
                                'status' => 'sent',
                            ]);

                            $user = $fresh->assignee?->user;
                            if ($user) {
                                $user->notify(new CrmTaskReminderNotification($fresh, $kind));
                            }

                            return true;
                        });

                        if ($created) {
                            $sent++;
                        }
                    } catch (UniqueConstraintViolationException) {
                        // Idempotent.
                    }
                }
            });

        $this->info("Sent {$sent} CRM task reminders.");

        return self::SUCCESS;
    }
}
