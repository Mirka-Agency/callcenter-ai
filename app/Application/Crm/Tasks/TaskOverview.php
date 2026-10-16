<?php

namespace App\Application\Crm\Tasks;

use App\Domain\Crm\Tasks\TaskStatus;
use App\Models\User;

class TaskOverview
{
    public function __construct(private TaskQuery $tasks) {}

    /** @return array<string, mixed> */
    public function summarize(int $organizationId, User $actor): array
    {
        $visible = $this->tasks->visible($organizationId, $actor);
        $open = (clone $visible)->whereIn('status', [TaskStatus::Pending->value, TaskStatus::InProgress->value]);
        $completed = (clone $visible)->where('status', TaskStatus::Completed);
        $completedWindow = (clone $completed)->where('completed_at', '>=', now()->subDays(30));
        $onTime = (clone $completedWindow)
            ->whereNotNull('due_at')
            ->whereColumn('completed_at', '<=', 'due_at')
            ->count();
        $completedCount = (clone $completedWindow)->count();
        $cancelledCount = (clone $visible)->where('status', TaskStatus::Cancelled)
            ->where('completed_at', '>=', now()->subDays(30))
            ->count();
        $closedForRate = $completedCount + $cancelledCount;

        return [
            'open_count' => (clone $open)->count(),
            'today_count' => (clone $open)->whereDate('due_at', now()->toDateString())->count(),
            'overdue_count' => (clone $open)->whereNotNull('due_at')->where('due_at', '<', now())->count(),
            'completed_30d' => $completedCount,
            'completion_rate_30d' => $closedForRate > 0
                ? round(($completedCount / $closedForRate) * 100, 1)
                : null,
            'on_time_rate_30d' => $completedCount > 0
                ? round(($onTime / $completedCount) * 100, 1)
                : null,
        ];
    }
}
