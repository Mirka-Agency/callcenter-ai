<?php

namespace App\Services\CallIntake\Contracts;

use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\VoipCallLog;
use Illuminate\Database\Eloquent\Builder;

/**
 * A built-in rule for which incoming calls are analyzed and counted.
 * Enabled means calls in this category pass through. Disabled means they are left out.
 * Add a new class and register it when an organization needs another rule.
 */
interface CallIntakeFilter
{
    public function key(): string;

    public function label(): string;

    public function description(): string;

    public function defaultEnabled(): bool;

    public function matches(Call $call): bool;

    public function skipReason(): string;

    /**
     * @param  Builder<Call>  $query
     */
    public function excludeFromCalls(Builder $query): void;

    /**
     * @param  Builder<ConversationAnalysis>  $query
     */
    public function excludeFromAnalyses(Builder $query): void;

    /**
     * @param  Builder<VoipCallLog>  $query
     */
    public function excludeFromVoipLogs(Builder $query, int $organizationId): void;
}
