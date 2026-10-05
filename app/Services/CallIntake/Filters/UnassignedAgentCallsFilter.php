<?php

namespace App\Services\CallIntake\Filters;

use App\Application\Call\Services\CallEmployeeResolver;
use App\Domain\Call\Enums\ConversationSource;
use App\Models\Call;
use App\Services\CallIntake\Contracts\CallIntakeFilter;
use App\Services\Reports\DefinedExtensionCallConstraint;
use Illuminate\Database\Eloquent\Builder;

class UnassignedAgentCallsFilter implements CallIntakeFilter
{
    public const KEY = 'unassigned_agent_calls';

    public function __construct(
        private CallEmployeeResolver $resolver,
        private DefinedExtensionCallConstraint $extensions,
    ) {}

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
        return 'اگر فعال باشد، تماسی که شماره داخلی‌اش در فهرست داخلی‌ها نیست هم تحلیل می‌شود و در شمارش تماس‌ها می‌آید.';
    }

    public function defaultEnabled(): bool
    {
        return true;
    }

    public function matches(Call $call): bool
    {
        if ($call->source !== ConversationSource::Voip) {
            return false;
        }

        if ($this->extensions->matchSetFingerprint((int) $call->organization_id) !== []) {
            return ! $this->extensions->countsForReports($call);
        }

        if ($call->organization_user_id) {
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
        return 'شماره داخلی این تماس در فهرست داخلی‌ها نیست و طبق فیلتر سازمان تحلیل نمی‌شود.';
    }

    public function excludeFromCalls(Builder $query): void
    {
        $table = $query->getModel()->getTable();

        $query->where(function (Builder $eligible) use ($table): void {
            $eligible->where($table.'.source', '!=', ConversationSource::Voip->value)
                ->orWhere($table.'.counts_for_extension_reports', true)
                ->orWhere(function (Builder $noDirectory) use ($table): void {
                    $noDirectory->whereNotNull($table.'.organization_user_id')
                        ->whereNotExists(function ($extensions) use ($table): void {
                            $extensions->selectRaw('1')
                                ->from('employee_integration_meta')
                                ->join('organization_user', 'organization_user.id', '=', 'employee_integration_meta.organization_user_id')
                                ->whereColumn('organization_user.organization_id', $table.'.organization_id')
                                ->where('employee_integration_meta.key', 'extension');
                        });
                });
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
                                ->orWhere('calls.counts_for_extension_reports', true)
                                ->orWhere(function ($noDirectory): void {
                                    $noDirectory->whereNotNull('calls.organization_user_id')
                                        ->whereNotExists(function ($extensions): void {
                                            $extensions->selectRaw('1')
                                                ->from('employee_integration_meta')
                                                ->join('organization_user', 'organization_user.id', '=', 'employee_integration_meta.organization_user_id')
                                                ->whereColumn('organization_user.organization_id', 'calls.organization_id')
                                                ->where('employee_integration_meta.key', 'extension');
                                        });
                                });
                        });
                });
        });
    }

    public function excludeFromVoipLogs(Builder $query, int $organizationId): void
    {
        $numbers = array_values(array_unique(array_merge(
            ...array_values($this->extensions->extensionNumbersByConnection($organizationId) ?: [[]]),
        )));

        if ($numbers === []) {
            return;
        }

        $query->where(function (Builder $eligible) use ($numbers): void {
            $eligible->whereIn('voip_call_logs.source_number', $numbers)
                ->orWhereIn('voip_call_logs.destination_number', $numbers);
        });
    }
}
