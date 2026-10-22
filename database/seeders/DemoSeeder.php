<?php

namespace Database\Seeders;

use Database\Seeders\Demo\DemoAnalyticsSeeder;
use Database\Seeders\Demo\DemoOrganizationsSeeder;
use Database\Seeders\Demo\DemoWorkspaceCoverageSeeder;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Creating demo organizations and users...');
        $this->call(DemoOrganizationsSeeder::class);

        $this->command?->info('Creating demo calls, customers, and analyses...');
        $this->call(DemoAnalyticsSeeder::class);

        $this->command?->info('Filling VoIP, CRM, uploads, and queue samples...');
        $this->call(DemoWorkspaceCoverageSeeder::class);

        $this->command?->newLine();
        $this->command?->info('Demo seed complete.');
        $this->command?->table(
            ['Item', 'Count'],
            [
                ['Organizations', \App\Support\Seeding\DemoCatalog::ORGANIZATION_COUNT],
                ['Demo users (employers + employees)', \App\Support\Seeding\DemoCatalog::ORGANIZATION_COUNT * (1 + \App\Support\Seeding\DemoCatalog::EMPLOYEES_PER_ORGANIZATION)],
                ['Calls per organization', \App\Support\Seeding\DemoCatalog::CALLS_PER_ORGANIZATION.' ('.\App\Support\Seeding\DemoCatalog::CALLS_TODAY_PER_ORGANIZATION.' today)'],
                ['Demo password (all accounts)', \App\Support\Seeding\DemoCatalog::DEMO_PASSWORD],
            ],
        );
        $this->command?->info(\App\Support\Seeding\DemoCatalog::credentialsSummary());
        $this->command?->info('Example: '.\App\Support\Seeding\DemoCatalog::exampleEmployerLogin());
    }
}
