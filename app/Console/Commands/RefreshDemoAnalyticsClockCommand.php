<?php

namespace App\Console\Commands;

use App\Services\Demo\DemoAnalyticsClock;
use Illuminate\Console\Command;

class RefreshDemoAnalyticsClockCommand extends Command
{
    protected $signature = 'demo:refresh-analytics-clock';

    protected $description = 'Shift stale demo call timestamps so "today" stays current';

    public function handle(DemoAnalyticsClock $clock): int
    {
        $shifted = $clock->refreshAllStale();

        $this->components->info(sprintf(
            'Refreshed demo analytics clock for %d organization(s).',
            $shifted,
        ));

        return self::SUCCESS;
    }
}
