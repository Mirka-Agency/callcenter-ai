<?php

namespace App\Providers;

use App\Application\Voip\Services\VoipConnectionResolver;
use App\Application\Voip\Services\VoipEventDeduplicator;
use App\Application\Voip\Services\VoipEventIngestionService;
use App\Application\Voip\Services\VoipWebhookDispatcher;
use App\Application\Voip\VoipManager;
use App\Domain\Voip\Contracts\VoipCallLogRepositoryInterface;
use App\Domain\Voip\Contracts\VoipConnectionRepositoryInterface;
use App\Domain\Voip\Contracts\VoipLogRepositoryInterface;
use App\Infrastructure\Voip\Repositories\EloquentVoipCallLogRepository;
use App\Infrastructure\Voip\Repositories\EloquentVoipConnectionRepository;
use App\Infrastructure\Voip\Repositories\EloquentVoipLogRepository;
use App\Infrastructure\Voip\VoipAdapterFactory;
use App\Infrastructure\Voip\VoipAdapterRegistry;
use Illuminate\Support\ServiceProvider;

class VoipServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(VoipAdapterFactory::class);
        $this->app->singleton(VoipAdapterRegistry::class);
        $this->app->singleton(VoipWebhookDispatcher::class);
        $this->app->singleton(VoipEventDeduplicator::class);
        $this->app->singleton(VoipEventIngestionService::class);

        $this->app->bind(VoipConnectionRepositoryInterface::class, EloquentVoipConnectionRepository::class);
        $this->app->bind(VoipLogRepositoryInterface::class, EloquentVoipLogRepository::class);
        $this->app->bind(VoipCallLogRepositoryInterface::class, EloquentVoipCallLogRepository::class);

        $this->app->bind(VoipManager::class, function ($app) {
            return new VoipManager(
                resolver: $app->make(VoipConnectionResolver::class),
                ingestion: $app->make(VoipEventIngestionService::class),
            );
        });
    }

    public function boot(): void
    {
        $factory = $this->app->make(VoipAdapterFactory::class);
        $factory->validateConfiguredAdapters();

        $this->app->make(VoipAdapterRegistry::class)->loadFromDatabase();
    }
}
