<?php

namespace App\Services\CallIntake\Filters;

use App\Application\Call\Services\CallEmployeeResolver;
use App\Domain\Call\Enums\ConversationSource;
use App\Models\Call;
use App\Services\CallIntake\Contracts\CallIntakeFilter;
use Illuminate\Database\Eloquent\Builder;

class UnassignedAgentCallsFilter implements CallIntakeFilter
{
    public const KEY = 'unassigned_agent_calls';

    public function __construct(private CallEmployeeResolver $resolver) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'تحلیل تماس‌هایی که کارشناس برایشان تعریف نشده';
    }

    public function description(): string
    {
        return 'اگر فعال باشد، تماسی که به هیچ کارشناسی وصل نیست هم تحلیل می‌شود و در شمارش تماس‌ها می‌آید.';
    }

    public function defaultEnabled(): bool
    {
        return true;
    }

    public function matches(Call $call): bool
    {
        if ($call->source !== ConversationSource::Voip || $call->organization_user_id) {
            return false;
        }

        $call->loadMissing('voipCallLog');
        $log = $call->voipCallLog;

        if ($log !== null && $this->resolver->resolveFromCallLog($log) !== null) {
            return false;
        }

        return true;
    }

    public function skipReason(): string
    {
        return 'برای این تماس کارشناسی تعریف نشده و طبق فیلتر سازمان تحلیل نمی‌شود.';
    }

    public function excludeFromCalls(Builder $query): void
    {
        $table = $query->getModel()->getTable();

        $query->where(function (Builder $eligible) use ($table): void {
            $eligible->where($table.'.source', '!=', ConversationSource::Voip->value)
                ->orWhereNotNull($table.'.organization_user_id')
                ->orWhere($table.'.counts_for_extension_reports', true);
        });
    }

    public function excludeFromAnalyses(Builder $query): void
    {
        $query->where(function (Builder $eligible): void {
            $eligible->whereNotNull('conversation_analyses.organization_user_id')
                ->orWhereExists(function ($call): void {
                    $call->selectRaw('1')
                        ->from('calls')
                        ->whereColumn('calls.id', 'conversation_analyses.call_id')
                        ->where(function ($inner): void {
                            $inner->where('calls.source', '!=', ConversationSource::Voip->value)
                                ->orWhere('calls.counts_for_extension_reports', true);
                        });
                });
        });
    }

    public function excludeFromVoipLogs(Builder $query, int $organizationId): void
    {
        unset($query, $organizationId);
    }
}
