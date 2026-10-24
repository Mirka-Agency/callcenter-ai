<?php

namespace App\Console\Commands;

use App\Services\Billing\BillingService;
use Illuminate\Console\Command;

class SyncBillingStatusesCommand extends Command
{
    protected $signature = 'billing:sync-statuses';

    protected $description = 'Mark subscriptions with overdue invoices as past due and expire ended trials';

    public function handle(BillingService $billing): int
    {
        $result = $billing->syncStatuses();

        $this->info("Past due: {$result['past_due']}, expired trials: {$result['expired']}.");

        return self::SUCCESS;
    }
}
