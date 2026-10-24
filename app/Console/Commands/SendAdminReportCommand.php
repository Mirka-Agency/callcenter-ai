<?php

namespace App\Console\Commands;

use App\Domain\Platform\Enums\AdminArea;
use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\Admin\AdminSummaryReportNotification;
use App\Services\Admin\AdminReportBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendAdminReportCommand extends Command
{
    protected $signature = 'admin:send-report {period=weekly : weekly or monthly}';

    protected $description = 'E-mail the management summary to super admins and analytics/finance admins';

    public function handle(AdminReportBuilder $builder): int
    {
        $period = (string) $this->argument('period');

        if (! in_array($period, [AdminReportBuilder::WEEKLY, AdminReportBuilder::MONTHLY], true)) {
            $this->error('Period must be weekly or monthly.');

            return self::INVALID;
        }

        $recipients = User::query()
            ->whereIn('role', [UserRole::SuperAdmin->value, UserRole::Admin->value])
            ->whereNotNull('email')
            ->get()
            ->filter(fn (User $user): bool => $user->hasAdminArea(AdminArea::Analytics) || $user->hasAdminArea(AdminArea::Finance));

        if ($recipients->isEmpty()) {
            $this->info('No recipients.');

            return self::SUCCESS;
        }

        Notification::send($recipients, new AdminSummaryReportNotification($builder->build($period)));

        $this->info("Sent {$period} report to {$recipients->count()} admins.");

        return self::SUCCESS;
    }
}
