<?php

namespace App\Services\Reports;

use App\Application\Call\Services\CallEmployeeResolver;
use App\Models\Call;
use App\Models\VoipCallLog;
use App\Support\CompanyWorkCalendar;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class OrganizationCallMetrics
{
    public function __construct(
        private CallEmployeeResolver $resolver,
    ) {}

    public function countToday(int $organizationId): int
    {
        return $this->countBetween(
            $organizationId,
            now()->startOfDay(),
            now()->endOfDay(),
        );
    }

    public function countThisMonth(int $organizationId): int
    {
        return $this->countBetween(
            $organizationId,
            now()->startOfMonth()->startOfDay(),
            now()->endOfDay(),
        );
    }

    public function countBetween(int $organizationId, Carbon $from, Carbon $to): int
    {
        $from = $from->copy();
        $to = $to->copy();

        $extensionMap = $this->resolver->extensionEmployeeMapForOrganization($organizationId);

        if ($extensionMap === []) {
            return Call::query()
                ->where('organization_id', $organizationId)
                ->occurredBetween($from, $to)
                ->whereNotNull('organization_user_id')
                ->count();
        }

        $linkedVoipLogIds = Call::query()
            ->where('organization_id', $organizationId)
            ->whereNotNull('voip_call_log_id')
            ->pluck('voip_call_log_id');

        $calls = Call::query()
            ->where('organization_id', $organizationId)
            ->occurredBetween($from, $to)
            ->with('voipCallLog.connection')
            ->get();

        $callCount = $calls
            ->filter(fn (Call $call): bool => $this->callUsesDefinedExtension($call, $extensionMap))
            ->count();

        $orphanCount = VoipCallLog::query()
            ->where('organization_id', $organizationId)
            ->occurredBetween($from, $to)
            ->when(
                $linkedVoipLogIds->isNotEmpty(),
                fn ($query) => $query->whereNotIn('id', $linkedVoipLogIds),
            )
            ->with('connection')
            ->get()
            ->filter(fn (VoipCallLog $log): bool => $this->resolver->resolveFromCallLogUsingMap($log, $extensionMap) !== null)
            ->count();

        return $callCount + $orphanCount;
    }

    /**
     * Tehran days that had at least one call on a registered extension.
     * Null means the organization has no extensions, so quiet days are not holidays.
     *
     * @return array<string, true>|null
     */
    public function extensionActivityDayKeys(int $organizationId, CarbonInterface $from, CarbonInterface $to): ?array
    {
        $extensionMap = $this->resolver->extensionEmployeeMapForOrganization($organizationId);

        if ($extensionMap === []) {
            return null;
        }

        $from = $from->copy()->timezone(CompanyWorkCalendar::TIMEZONE)->startOfDay()->utc();
        $to = $to->copy()->timezone(CompanyWorkCalendar::TIMEZONE)->endOfDay()->utc();
        $days = [];

        $calls = Call::query()
            ->where('organization_id', $organizationId)
            ->occurredBetween($from, $to)
            ->with('voipCallLog')
            ->get([
                'id',
                'organization_voip_connection_id',
                'voip_call_log_id',
                'caller_number',
                'receiver_number',
                'conversation_date',
                'started_at',
                'created_at',
            ]);

        foreach ($calls as $call) {
            if (! $this->callUsesDefinedExtension($call, $extensionMap)) {
                continue;
            }

            $occurredAt = $call->occurredAt();

            if ($occurredAt !== null) {
                $days[CompanyWorkCalendar::dayKey($occurredAt)] = true;
            }
        }

        $logs = VoipCallLog::query()
            ->where('organization_id', $organizationId)
            ->occurredBetween($from, $to)
            ->with('connection')
            ->get();

        foreach ($logs as $log) {
            if ($this->resolver->resolveFromCallLogUsingMap($log, $extensionMap) === null) {
                continue;
            }

            $occurredAt = $log->started_at ?? $log->created_at;

            if ($occurredAt !== null) {
                $days[CompanyWorkCalendar::dayKey($occurredAt)] = true;
            }
        }

        return $days;
    }

    /**
     * A call counts only when it was placed on an extension assigned to an employee.
     *
     * @param  array<string, int>  $extensionMap
     */
    private function callUsesDefinedExtension(Call $call, array $extensionMap): bool
    {
        $log = $call->voipCallLog;

        if ($log !== null) {
            return $this->resolver->resolveFromCallLogUsingMap($log, $extensionMap) !== null;
        }

        $connectionId = (int) $call->organization_voip_connection_id;

        if ($connectionId === 0) {
            return false;
        }

        foreach ([$call->receiver_number, $call->caller_number] as $number) {
            $number = trim((string) $number);

            if ($number !== '' && isset($extensionMap[$connectionId.'|'.$number])) {
                return true;
            }
        }

        return false;
    }
}
