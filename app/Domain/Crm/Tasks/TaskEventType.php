<?php

namespace App\Domain\Crm\Tasks;

enum TaskEventType: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Assigned = 'assigned';
    case Started = 'started';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Reopened = 'reopened';
    case Rescheduled = 'rescheduled';
    case PriorityChanged = 'priority_changed';
    case NoteAdded = 'note_added';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'ایجاد وظیفه',
            self::Updated => 'ویرایش وظیفه',
            self::Assigned => 'تخصیص مسئول',
            self::Started => 'شروع انجام',
            self::Completed => 'تکمیل',
            self::Cancelled => 'لغو',
            self::Reopened => 'بازگشایی',
            self::Rescheduled => 'تغییر موعد',
            self::PriorityChanged => 'تغییر اولویت',
            self::NoteAdded => 'یادداشت',
        };
    }
}
