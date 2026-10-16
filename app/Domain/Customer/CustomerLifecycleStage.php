<?php

namespace App\Domain\Customer;

enum CustomerLifecycleStage: string
{
    case NewLead = 'new_lead';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case Customer = 'customer';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::NewLead => 'سرنخ جدید',
            self::Contacted => 'ارتباط برقرارشده',
            self::Qualified => 'واجد شرایط',
            self::Customer => 'مشتری',
            self::Inactive => 'غیرفعال',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::NewLead => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-300',
            self::Contacted => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-200',
            self::Qualified => 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
            self::Customer => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
            self::Inactive => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300',
        };
    }

    public static function unknownLabel(): string
    {
        return 'نامشخص';
    }

    public static function unknownBadgeClass(): string
    {
        return 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300';
    }
}
