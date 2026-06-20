<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Support\Seeding\DemoAnalyticsBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProvisionDemoOrganizationJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(private readonly int $organizationId) {}

    public function handle(DemoAnalyticsBuilder $builder): void
    {
        $organization = Organization::find($this->organizationId);

        if ($organization === null || ! $organization->is_demo) {
            return;
        }

        $builder->seedForOrganization($organization, 1);
    }
}
