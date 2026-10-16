<?php

namespace App\Providers;

use App\Application\Crm\CrmManager;
use App\Application\Crm\Deals\Listeners\SuggestDealFromAnalysisListener;
use App\Application\Crm\Tasks\Listeners\SuggestTasksFromAnalysisListener;
use App\Application\Crm\Services\CrmConnectionResolver;
use App\Domain\Crm\Contracts\CrmConnectionRepositoryInterface;
use App\Domain\Crm\Contracts\CrmLogRepositoryInterface;
use App\Domain\Customer\Events\CustomerSyncedFromAnalysis;
use App\Infrastructure\Crm\CrmAdapterRegistry;
use App\Infrastructure\Crm\Repositories\EloquentCrmConnectionRepository;
use App\Infrastructure\Crm\Repositories\EloquentCrmLogRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class CrmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CrmAdapterRegistry::class);

        $this->app->bind(CrmConnectionRepositoryInterface::class, EloquentCrmConnectionRepository::class);
        $this->app->bind(CrmLogRepositoryInterface::class, EloquentCrmLogRepository::class);

        $this->app->bind(CrmManager::class, function ($app) {
            return new CrmManager(
                resolver: $app->make(CrmConnectionResolver::class),
                logs: $app->make(CrmLogRepositoryInterface::class),
            );
        });
    }

    public function boot(): void
    {
        Event::listen(CustomerSyncedFromAnalysis::class, SuggestDealFromAnalysisListener::class);
        Event::listen(CustomerSyncedFromAnalysis::class, SuggestTasksFromAnalysisListener::class);
    }
}
