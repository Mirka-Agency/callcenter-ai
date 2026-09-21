<?php

namespace App\Providers;

use App\Application\AiUsage\Listeners\RecordAiUsageSnapshot;
use App\Application\Customer\Listeners\SyncCustomerFromAnalysis;
use App\Application\Llm\AnalysisManager;
use App\Application\Llm\Services\AudioAnalyzer;
use App\Application\Llm\Services\LlmConnectionResolver;
use App\Domain\Llm\Contracts\ConversationAnalysisRepositoryInterface;
use App\Domain\Llm\Contracts\LlmConnectionRepositoryInterface;
use App\Domain\Llm\Contracts\LlmLogRepositoryInterface;
use App\Domain\Llm\Events\ConversationAnalyzed;
use App\Infrastructure\Llm\LlmAdapterRegistry;
use App\Infrastructure\Llm\LlmOutboundGuard;
use App\Infrastructure\Llm\Repositories\EloquentConversationAnalysisRepository;
use App\Infrastructure\Llm\Repositories\EloquentLlmConnectionRepository;
use App\Infrastructure\Llm\Repositories\EloquentLlmLogRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

class LlmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LlmAdapterRegistry::class);

        $this->app->bind(LlmConnectionRepositoryInterface::class, EloquentLlmConnectionRepository::class);
        $this->app->bind(ConversationAnalysisRepositoryInterface::class, EloquentConversationAnalysisRepository::class);
        $this->app->bind(LlmLogRepositoryInterface::class, EloquentLlmLogRepository::class);

        $this->app->singleton(AudioAnalyzer::class);

        $this->app->bind(AnalysisManager::class, function ($app) {
            return new AnalysisManager(
                resolver: $app->make(LlmConnectionResolver::class),
                logs: $app->make(LlmLogRepositoryInterface::class),
                audioAnalyzer: $app->make(AudioAnalyzer::class),
            );
        });
    }

    public function boot(): void
    {
        Http::globalRequestMiddleware(function ($request) {
            app(LlmOutboundGuard::class)->assertUrlAllowed((string) $request->getUri());

            return $request;
        });

        Event::listen(ConversationAnalyzed::class, RecordAiUsageSnapshot::class);
        Event::listen(ConversationAnalyzed::class, SyncCustomerFromAnalysis::class);
    }
}
