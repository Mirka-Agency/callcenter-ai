<?php

use App\Application\Transcription\Jobs\CleanupSonioxTranscription;
use App\Infrastructure\Llm\LlmOutboundGuard;
use App\Models\CallTranscript;
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
Schedule::command('demo:refresh-analytics-clock')->daily();
Schedule::command('voip:resolve-outcomes')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('crm:tasks-send-reminders')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('admin:check-alerts')->everyTenMinutes()->withoutOverlapping();
Schedule::command('billing:sync-statuses')->dailyAt('01:00');
Schedule::command('admin:send-report weekly')->weeklyOn(6, '08:00');
Schedule::command('admin:send-report monthly')->monthlyOn(1, '08:00');

Artisan::command('transcriptions:cleanup', function () {
    if (! app(LlmOutboundGuard::class)->remoteAnalysisEnabled()) {
        $this->info('Remote processing is disabled; cleanup was not queued.');

        return;
    }

    CallTranscript::query()
        ->where('transcription_provider', 'soniox')
        ->whereIn('status', [CallTranscript::STATUS_COMPLETED, CallTranscript::STATUS_FAILED])
        ->whereNull('remote_cleaned_at')
        ->whereNotNull('remote_file_id')
        ->chunkById(100, function ($transcripts) {
            foreach ($transcripts as $transcript) {
                CleanupSonioxTranscription::dispatch($transcript->id);
            }
        });
})->purpose('Retry cleanup of terminal Soniox transcription resources');

Schedule::command('transcriptions:cleanup')->hourly()->withoutOverlapping();
