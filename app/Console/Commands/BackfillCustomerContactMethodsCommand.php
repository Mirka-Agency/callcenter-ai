<?php

namespace App\Console\Commands;

use App\Application\Customer\CustomerContactMethodMirror;
use App\Models\Customer;
use App\Services\CustomerPhoneResolver;
use Illuminate\Console\Command;

class BackfillCustomerContactMethodsCommand extends Command
{
    protected $signature = 'customers:backfill-contact-methods {--dry-run : فقط گزارش، بدون نوشتن} {--chunk=200}';

    protected $description = 'Mirror existing customer phone and email onto contact methods without deleting records';

    public function handle(CustomerContactMethodMirror $mirror, CustomerPhoneResolver $phones): int
    {
        $invalid = 0;
        $ready = 0;

        Customer::query()->orderBy('id')->chunkById((int) $this->option('chunk'), function ($customers) use ($mirror, $phones, &$invalid, &$ready): void {
            foreach ($customers as $customer) {
                $inspection = $phones->inspect($customer->phone_number ?: $customer->normalized_phone);
                if (! $inspection->isValid) {
                    $invalid++;
                    $this->line("invalid-phone customer={$customer->id} organization={$customer->organization_id}");

                    continue;
                }

                $ready++;

                if (! $this->option('dry-run')) {
                    $mirror->mirrorFromCustomer($customer);
                }
            }
        });

        $this->info("ready={$ready} invalid={$invalid} dry_run=".($this->option('dry-run') ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}
