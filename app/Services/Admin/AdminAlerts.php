<?php

namespace App\Services\Admin;

use App\Domain\Platform\Enums\AdminArea;
use App\Enums\UserRole;
use App\Filament\Pages\Finance\FinanceOverview;
use App\Filament\Pages\System\QueueMonitor;
use App\Models\User;
use App\Services\Billing\BillingMetrics;
use App\Services\QueueMonitoring\QueueMonitorStats;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * In-app (Filament database) alerts for admins. The same problem is not re-sent
 * within the cooldown window.
 */
class AdminAlerts
{
    public const COOLDOWN_HOURS = 6;

    public const QUEUE_BACKLOG_THRESHOLD = 500;

    public function __construct(
        private readonly ProblemInbox $inbox,
        private readonly BillingMetrics $billing,
    ) {}

    public function send(AdminArea $area, string $title, ?string $body = null, ?string $url = null, string $severity = 'warning', ?string $dedupeKey = null): int
    {
        if ($dedupeKey !== null && ! Cache::add('admin-alert:'.$dedupeKey, true, now()->addHours(self::COOLDOWN_HOURS))) {
            return 0;
        }

        $recipients = $this->recipients($area);

        if ($recipients->isEmpty()) {
            return 0;
        }

        $notification = Notification::make()
            ->title($title)
            ->body($body)
            ->icon($severity === 'danger' ? 'heroicon-o-exclamation-circle' : 'heroicon-o-bell-alert')
            ->iconColor($severity === 'danger' ? 'danger' : 'warning');

        if ($url) {
            $notification->actions([Action::make('open')->label(__('admin.common.open'))->url($url)->markAsRead()]);
        }

        $notification->sendToDatabase($recipients);

        return $recipients->count();
    }

    /**
     * Scheduled check: urgent inbox items, queue backlog and AI budget.
     *
     * @return int number of alerts sent
     */
    public function check(): int
    {
        $sent = 0;

        foreach ($this->inbox->items() as $item) {
            if ($item['severity'] !== ProblemInbox::DANGER) {
                continue;
            }

            $sent += $this->send($item['area'], $item['title'], $item['detail'], $item['url'], 'danger', 'inbox:'.$item['key']) > 0 ? 1 : 0;
        }

        $pending = (int) (app(QueueMonitorStats::class)->overview()['pending'] ?? 0);

        if ($pending >= self::QUEUE_BACKLOG_THRESHOLD) {
            $sent += $this->send(
                AdminArea::Support,
                __('admin.alerts.queue_backlog', ['count' => $pending]),
                null,
                QueueMonitor::getUrl(),
                'danger',
                'queue-backlog',
            ) > 0 ? 1 : 0;
        }

        $margins = $this->billing->margins();

        if ($margins['budget'] && $margins['total_cost'] >= 0.8 * $margins['budget']) {
            $over = $margins['total_cost'] >= $margins['budget'];
            $sent += $this->send(
                AdminArea::Finance,
                __($over ? 'admin.alerts.ai_budget_exceeded' : 'admin.alerts.ai_budget_near'),
                null,
                FinanceOverview::getUrl(),
                $over ? 'danger' : 'warning',
                'ai-budget:'.($over ? 'over' : 'near').':'.now()->format('Y-m'),
            ) > 0 ? 1 : 0;
        }

        return $sent;
    }

    /** @return Collection<int, User> */
    private function recipients(AdminArea $area): Collection
    {
        return User::query()
            ->whereIn('role', [UserRole::SuperAdmin->value, UserRole::Admin->value])
            ->get()
            ->filter(fn (User $user): bool => $user->hasAdminArea($area))
            ->values();
    }
}
