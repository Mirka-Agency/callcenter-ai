<?php

namespace App\Support;

class CoachingPresenter
{
    public static function statusBadgeClass(string $status): string
    {
        return match ($status) {
            'strength' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
            'good' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-300',
            'needs_improvement' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
            'critical' => 'bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-300',
            default => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300',
        };
    }

    public static function severityBadgeClass(string $severity): string
    {
        return match ($severity) {
            'high' => 'bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-300',
            'medium' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
            default => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300',
        };
    }
}
