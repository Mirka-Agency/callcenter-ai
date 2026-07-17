<?php

namespace Database\Seeders;

use App\Services\IntegrationMetaDefinitionSynchronizer;
use Illuminate\Database\Seeder;

class IntegrationMetaDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        app(IntegrationMetaDefinitionSynchronizer::class)->syncAll();
    }
}
