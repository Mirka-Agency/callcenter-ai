<?php

namespace App\Notifications;

use App\Models\CrmTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CrmTaskAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public CrmTask $task)
    {
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
        return [
            'type' => 'crm_task_assigned',
            'crm_task_id' => $this->task->id,
            'organization_id' => $this->task->organization_id,
            'title' => $this->task->title,
            'message' => 'یک وظیفه CRM به شما تخصیص داده شد: '.$this->task->title,
            'due_at' => $this->task->due_at?->toIso8601String(),
        ];
    }
}
