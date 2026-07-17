<?php

use App\Services\IntegrationMetaDefinitionSynchronizer;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('integrations:sync-meta-definitions', function () {
    $count = app(IntegrationMetaDefinitionSynchronizer::class)->syncAll();

    $this->info("Synchronized {$count} integration meta definitions.");
})->purpose('Sync employee integration fields declared by provider adapters');

Schedule::command('recordings:purge-expired')->daily();
