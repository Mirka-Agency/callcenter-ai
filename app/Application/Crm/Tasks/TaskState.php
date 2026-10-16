<?php

namespace App\Application\Crm\Tasks;

use App\Domain\Crm\Tasks\TaskStatus;
use App\Models\CrmTask;
use Illuminate\Validation\ValidationException;

class TaskState
{
    public static function assertOpen(CrmTask $task): void
    {
        if (! $task->status->isOpen()) {
            throw ValidationException::withMessages([
                'status' => 'این عملیات فقط برای وظایف باز مجاز است.',
            ]);
        }
    }

    public static function assertCanComplete(CrmTask $task): void
    {
        if (! $task->status->isOpen()) {
            throw ValidationException::withMessages([
                'status' => 'فقط وظایف باز را می‌توان تکمیل کرد. در صورت نیاز ابتدا بازگشایی کنید.',
            ]);
        }
    }

    public static function assertCanCancel(CrmTask $task): void
    {
        if (! $task->status->isOpen()) {
            throw ValidationException::withMessages([
                'status' => 'فقط وظایف باز را می‌توان لغو کرد.',
            ]);
        }
    }

    public static function assertCanReopen(CrmTask $task): void
    {
        if (! $task->status->isTerminal()) {
            throw ValidationException::withMessages([
                'status' => 'فقط وظایف تکمیل‌شده یا لغوشده قابل بازگشایی هستند.',
            ]);
        }
    }

    public static function assertTransition(CrmTask $task, TaskStatus $to): void
    {
        $from = $task->status;

        $allowed = match ($from) {
            TaskStatus::Pending => [TaskStatus::InProgress, TaskStatus::Completed, TaskStatus::Cancelled],
            TaskStatus::InProgress => [TaskStatus::Pending, TaskStatus::Completed, TaskStatus::Cancelled],
            TaskStatus::Completed, TaskStatus::Cancelled => [TaskStatus::Pending],
        };

        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => 'انتقال وضعیت «'.$from->label().'» به «'.$to->label().'» مجاز نیست.',
            ]);
        }
    }
}
