<?php

namespace App\Domain\Crm\Tasks;

use Carbon\CarbonInterface;

/**
 * Internal CRM due suggestion. Mirrors external CRM lead-score windows
 * without writing anything to Didar/Dynamics.
 */
final class TaskDuePolicy
{
    public static function fromLeadScore(?int $score, ?CarbonInterface $from = null): CarbonInterface
    {
        $from ??= now();

        if ($score !== null && $score >= 70) {
            return $from->copy()->addHours(4);
        }

        if ($score !== null && $score >= 40) {
            return $from->copy()->addDay();
        }

        return $from->copy()->addDays(3);
    }
}
