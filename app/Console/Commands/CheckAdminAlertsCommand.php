<?php

namespace App\Console\Commands;

use App\Services\Admin\AdminAlerts;
use Illuminate\Console\Command;

class CheckAdminAlertsCommand extends Command
{
    protected $signature = 'admin:check-alerts';

    protected $description = 'Send in-app admin alerts for urgent problems, queue backlog and AI budget';

    public function handle(AdminAlerts $alerts): int
    {
        $this->info('Sent '.$alerts->check().' alerts.');

        return self::SUCCESS;
    }
}
