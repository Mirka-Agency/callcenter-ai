<?php

namespace Database\Seeders\Demo;

use App\Models\Organization;
use App\Support\Seeding\DemoInternalCrm;
use Illuminate\Database\Seeder;

class DemoInternalCrmSeeder extends Seeder
{
    public function run(): void
    {
        Organization::query()
            ->where('is_demo', true)
            ->orderBy('id')
            ->each(fn (Organization $organization) => app(DemoInternalCrm::class)->seed($organization));
    }
}
