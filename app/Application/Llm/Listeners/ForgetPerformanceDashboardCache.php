<?php

namespace App\Application\Llm\Listeners;

use App\Domain\Llm\Events\ConversationAnalyzed;
use App\Services\Performance\EmployeePerformanceAnalytics;

class ForgetPerformanceDashboardCache
{
    public function handle(ConversationAnalyzed $event): void
    {
        EmployeePerformanceAnalytics::forgetOrganizationCaches($event->organizationId);
    }
}
