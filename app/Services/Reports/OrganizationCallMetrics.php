<?php

namespace App\Services\Reports;

use App\Application\Call\Services\CallEmployeeResolver;
use App\Models\Call;
use App\Models\VoipCallLog;
use Carbon\Carbon;

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
