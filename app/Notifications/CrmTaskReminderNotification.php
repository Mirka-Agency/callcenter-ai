<?php

namespace App\Notifications;

use App\Models\CrmTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CrmTaskReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public CrmTask $task,
        public string $kind,
    ) {
        $this->onQueue('followup');
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $label = $this->kind === 'overdue' ? 'عقب‌افتاده' : 'نزدیک موعد';

        return [
            'type' => 'crm_task_reminder',
            'kind' => $this->kind,
            'crm_task_id' => $this->task->id,
            'organization_id' => $this->task->organization_id,
            'title' => $this->task->title,
            'message' => 'یادآوری وظیفه '.$label.': '.$this->task->title,
            'due_at' => $this->task->due_at?->toIso8601String(),
        ];
    }
}
