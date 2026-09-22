<?php

namespace App\Support;

use App\Domain\Voip\Enums\CallDirection;
use App\Domain\Voip\Enums\CallStatus;
use App\Models\ConversationAnalysis;
use App\Services\Reports\CallMetricsAnalytics;
use Carbon\CarbonInterface;

class AnalysisCallPresenter
{
    /**
     * Day the call took place (not when it was analyzed).
     */
    public static function callOccurredAt(ConversationAnalysis $analysis): ?CarbonInterface
    {
        return $analysis->call?->occurredAt();
    }

    public static function status(ConversationAnalysis $analysis): ?CallStatus
    {
        $raw = $analysis->call?->status ?? $analysis->callLog?->status?->value;

        return $raw ? CallStatus::tryFrom($raw) : null;
    }

    public static function direction(ConversationAnalysis $analysis): ?CallDirection
    {
        $raw = $analysis->call?->direction ?? $analysis->callLog?->direction?->value;

        return $raw ? CallDirection::tryFrom($raw) : null;
    }

    public static function durationSeconds(ConversationAnalysis $analysis): ?int
    {
        return $analysis->call?->duration_seconds ?? $analysis->callLog?->duration;
    }

    public static function durationLabel(ConversationAnalysis $analysis): string
    {
        return app(CallMetricsAnalytics::class)->formatDuration(self::durationSeconds($analysis) ?? 0);
    }

    public static function statusBadgeClass(?CallStatus $status): string
    {
        return match ($status) {
            CallStatus::Completed, CallStatus::Answered => 'saas-badge-success',
            CallStatus::Missed, CallStatus::Failed, CallStatus::Busy => 'saas-badge-danger',
            CallStatus::Cancelled => 'saas-badge-warning',
            default => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
        };
    }
}
