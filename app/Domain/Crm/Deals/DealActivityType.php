<?php

namespace App\Domain\Crm\Deals;

enum DealActivityType: string
{
    case Created = 'created';
    case Updated = 'updated';
    case StageChanged = 'stage_changed';
    case OwnerChanged = 'owner_changed';
    case Won = 'won';
    case Lost = 'lost';
    case Reopened = 'reopened';
    case Archived = 'archived';
    case CallLinked = 'call_linked';
    case SuggestionAccepted = 'suggestion_accepted';
    case ValueChanged = 'value_changed';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'ایجاد معامله',
            self::Updated => 'ویرایش معامله',
            self::StageChanged => 'تغییر مرحله',
            self::OwnerChanged => 'تغییر مسئول',
            self::Won => 'ثبت موفق',
            self::Lost => 'ثبت ناموفق',
            self::Reopened => 'بازگشایی',
            self::Archived => 'بایگانی',
            self::CallLinked => 'اتصال تماس',
            self::SuggestionAccepted => 'تأیید پیشنهاد',
            self::ValueChanged => 'تغییر مبلغ',
        };
    }
}
