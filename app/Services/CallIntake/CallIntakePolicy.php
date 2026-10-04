<?php

namespace App\Services\CallIntake;

use App\Domain\Call\Enums\ConversationSource;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\VoipCallLog;
use App\Services\CallIntake\Contracts\CallIntakeFilter;
use App\Services\CallIntake\Filters\UnassignedAgentCallsFilter;
use Illuminate\Database\Eloquent\Builder;

class CallIntakePolicy
{
    public function __construct(
        private CallIntakeFilterRegistry $registry,
        private CallIntakeSettings $settings,
    ) {}

    /**
     * Count cards that stay outside these filters:
     * تماس‌های خارج از تحلیل، لیدها، و اعداد کیف پول (هزینه، توکن، تحلیل‌های صورتحساب).
     * Counts that are not calls — active employees, connections, contacts — are untouched.
     */
    public function rejectionReason(Call $call): ?string
    {
        if ($call->source !== ConversationSource::Voip) {
            return null;
        }

        foreach ($this->blockingFilters($call) as $filter) {
            return $filter->skipReason();
        }

        return null;
    }

    public function allowsUnassigned(Call $call): bool
    {
        return $call->source === ConversationSource::Voip
            && ! $call->organization_user_id
            && $this->settings->enabled((int) $call->organization_id, UnassignedAgentCallsFilter::KEY);
    }

    /**
     * @param  Builder<Call>  $query
     * @return Builder<Call>
     */
    public function applyToCalls(Builder $query, int $organizationId): Builder
    {
        foreach ($this->registry->all() as $filter) {
            if ($this->settings->enabled($organizationId, $filter->key())) {
                continue;
            }

            $filter->excludeFromCalls($query);
        }

        return $query;
    }

    /**
     * @param  Builder<ConversationAnalysis>  $query
     * @return Builder<ConversationAnalysis>
     */
    public function applyToAnalyses(Builder $query, int $organizationId): Builder
    {
        foreach ($this->registry->all() as $filter) {
            if ($this->settings->enabled($organizationId, $filter->key())) {
                continue;
            }

            $filter->excludeFromAnalyses($query);
        }

        return $query;
    }

    /**
     * @param  Builder<VoipCallLog>  $query
     * @return Builder<VoipCallLog>
     */
    public function applyToVoipLogs(Builder $query, int $organizationId): Builder
    {
        foreach ($this->registry->all() as $filter) {
            if ($this->settings->enabled($organizationId, $filter->key())) {
                continue;
            }

            $filter->excludeFromVoipLogs($query, $organizationId);
        }

        return $query;
    }

    /**
     * @return list<CallIntakeFilter>
     */
    private function blockingFilters(Call $call): array
    {
        $blocking = [];

        foreach ($this->registry->all() as $filter) {
            if ($this->settings->enabled((int) $call->organization_id, $filter->key())) {
                continue;
            }

            if ($filter->matches($call)) {
                $blocking[] = $filter;
            }
        }

        return $blocking;
    }
}
