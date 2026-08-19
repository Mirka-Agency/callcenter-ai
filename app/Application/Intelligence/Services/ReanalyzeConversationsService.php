<?php

namespace App\Application\Intelligence\Services;

use App\Domain\Intelligence\Enums\ReanalyzeScope;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Organization;
use Carbon\CarbonInterface;

class ReanalyzeConversationsService
{
    public function __construct(
        private CallAnalysisQueueService $queue,
    ) {}

    public function queue(
        Organization $organization,
        ReanalyzeScope $scope,
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
    ): int {
        $callIds = ConversationAnalysis::query()
            ->where('organization_id', $organization->id)
            ->whereNotNull('call_id')
            ->when(
                $scope->maxScore() !== null,
                fn ($query) => $query->where('score', '<=', $scope->maxScore()),
            )
            ->when($from, fn ($query) => $query->where('analyzed_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($query) => $query->where('analyzed_at', '<=', $to->copy()->endOfDay()))
            ->distinct()
            ->pluck('call_id');

        if ($callIds->isEmpty()) {
            return 0;
        }

        $queued = 0;

        Call::query()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $callIds)
            ->with(['voipCallLog', 'recording'])
            ->orderBy('id')
            ->each(function (Call $call) use (&$queued): void {
                if ($this->queue->dispatchForCall($call, forceReanalyze: true)) {
                    $queued++;
                }
            });

        return $queued;
    }
}
