<?php

namespace App\Services\CallIntake\Filters;

use App\Domain\Call\Enums\ConversationSource;
use App\Models\Call;
use App\Services\CallIntake\Contracts\CallIntakeFilter;
use App\Services\CallIntake\InternalAgentCallDetector;
use App\Services\Reports\DefinedExtensionCallConstraint;
use Illuminate\Database\Eloquent\Builder;

class InternalAgentCallsFilter implements CallIntakeFilter
{
    public const KEY = 'internal_agent_calls';

    public function __construct(
        private InternalAgentCallDetector $detector,
        private DefinedExtensionCallConstraint $extensions,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'تحلیل تماس بین دو کارشناس داخلی';
    }

    public function description(): string
    {
        return 'اگر فعال باشد، مکالمه بین دو کارشناس داخلی سازمان تحلیل می‌شود و در شمارش تماس‌ها می‌آید.';
    }

    public function defaultEnabled(): bool
    {
        return true;
    }

    public function matches(Call $call): bool
    {
        return $this->detector->matches($call);
    }

    public function skipReason(): string
    {
        return 'تماس بین دو کارشناس داخلی است و طبق فیلتر سازمان تحلیل نمی‌شود.';
    }

    public function excludeFromCalls(Builder $query): void
    {
        $table = $query->getModel()->getTable();

        $query->where(function (Builder $eligible) use ($table): void {
            $eligible->where($table.'.source', '!=', ConversationSource::Voip->value)
                ->orWhere($table.'.is_internal_agent_call', false);
        });
    }

    public function excludeFromAnalyses(Builder $query): void
    {
        $query->where(function (Builder $eligible): void {
            $eligible->whereNull('conversation_analyses.call_id')
                ->orWhereExists(function ($call): void {
                    $call->selectRaw('1')
                        ->from('calls')
                        ->whereColumn('calls.id', 'conversation_analyses.call_id')
                        ->where(function ($inner): void {
                            $inner->where('calls.source', '!=', ConversationSource::Voip->value)
                                ->orWhere('calls.is_internal_agent_call', false);
                        });
                });
        });
    }

    public function excludeFromVoipLogs(Builder $query, int $organizationId): void
    {
        $sets = array_filter(
            $this->extensions->extensionNumbersByConnection($organizationId),
            fn (array $numbers): bool => $numbers !== [],
        );

        if ($sets === []) {
            return;
        }

        $query->where(function (Builder $eligible) use ($sets): void {
            $eligible->whereNot(function (Builder $internal) use ($sets): void {
                foreach ($sets as $connectionId => $numbers) {
                    if ($numbers === []) {
                        continue;
                    }

                    $internal->orWhere(function (Builder $side) use ($connectionId, $numbers): void {
                        $side->where('voip_call_logs.organization_voip_connection_id', $connectionId)
                            ->whereIn('voip_call_logs.source_number', $numbers)
                            ->whereIn('voip_call_logs.destination_number', $numbers);
                    });
                }
            });
        });
    }
}
