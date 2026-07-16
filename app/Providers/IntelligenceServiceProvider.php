<?php

namespace App\Providers;

use App\Application\Call\Services\CallIngestionService;
use App\Application\Call\Services\ManualAudioUploadService;
use App\Application\Crm\Services\CrmIntelligenceSyncService;
use App\Application\Intelligence\Listeners\BroadcastIncomingCall;
use App\Application\Intelligence\Listeners\StartCallIntelligenceAnalysis;
use App\Domain\Call\Contracts\CallRepositoryInterface;
use App\Domain\Performance\Contracts\EmployeePerformanceRepositoryInterface;
use App\Domain\Recording\Contracts\RecordingDownloaderInterface;
use App\Domain\Recording\Contracts\RecordingRepositoryInterface;
use App\Domain\Voip\Events\CallEnded;
use App\Domain\Voip\Events\CallStarted;
use App\Domain\Voip\Events\RecordingCreated;
use App\Infrastructure\Call\Repositories\EloquentCallRepository;
use App\Infrastructure\Performance\Repositories\EloquentEmployeePerformanceRepository;
use App\Infrastructure\Recording\Repositories\EloquentRecordingRepository;
use App\Infrastructure\Recording\VoipAwareRecordingDownloader;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class IntelligenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CallRepositoryInterface::class, EloquentCallRepository::class);
        $this->app->bind(RecordingRepositoryInterface::class, EloquentRecordingRepository::class);
        $this->app->bind(RecordingDownloaderInterface::class, VoipAwareRecordingDownloader::class);
        $this->app->bind(EmployeePerformanceRepositoryInterface::class, EloquentEmployeePerformanceRepository::class);

        $this->app->singleton(CallIngestionService::class);
        $this->app->singleton(ManualAudioUploadService::class);
        $this->app->singleton(CrmIntelligenceSyncService::class);
    }

    public function boot(): void
    {
        $listener = StartCallIntelligenceAnalysis::class;

        Event::listen(CallStarted::class, BroadcastIncomingCall::class);
        Event::listen(CallEnded::class, [$listener, 'handleVoipEvent']);
        Event::listen(RecordingCreated::class, [$listener, 'handleVoipEvent']);
    }
}
