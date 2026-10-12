<?php

namespace Database\Seeders\Demo;

use App\Models\Organization;
use App\Support\Seeding\DemoWorkspaceCoverage;
use Illuminate\Database\Seeder;

class DemoWorkspaceCoverageSeeder extends Seeder
{
    public function run(): void
    {
        $coverage = app(DemoWorkspaceCoverage::class);

        Organization::query()
            ->where('is_demo', true)
            ->orderBy('id')
            ->each(function (Organization $organization) use ($coverage): void {
                $this->command?->info("Seeding workspace samples for {$organization->title}...");
                $coverage->seed($organization);
            });
    }
}
