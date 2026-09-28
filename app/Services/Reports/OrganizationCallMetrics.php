<?php

namespace App\Services\Reports;

use App\Application\Call\Services\CallEmployeeResolver;
use App\Domain\Voip\Enums\CallStatus;
use App\Models\Call;
use App\Models\VoipCallLog;
use App\Support\CompanyWorkCalendar;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OrganizationCallMetrics
{
    /** @var array<string, array<string, true>|null> */
    private array $activityDays = [];

    public function __construct(
        private CallEmployeeResolver $resolver,
        private DefinedExtensionCallConstraint $definedExtensions,
    ) {}

    public function countToday(int $organizationId): int
    {
        return Cache::remember(
            'calls-today:'.$organizationId.':'.now()->toDateString().':recorded',
            60,
            fn (): int => $this->countBetween(
                $organizationId,
                now()->startOfDay(),
                now()->endOfDay(),
            ),
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
                ->withRecording()
                ->count();
        }

        $callCount = $this->definedExtensions->apply(
            Call::query()
                ->where('organization_id', $organizationId)
                ->occurredBetween($from, $to),
            $organizationId,
        )->count();

        $orphanCount = $this->definedExtensions->applyToVoipLogs(
            $this->orphanLogQuery($organizationId, $from, $to),
            $organizationId,
        )->count();

        return $callCount + $orphanCount;
    }

    public function countLost(int $organizationId): int
    {
        $extensionMap = $this->resolver->extensionEmployeeMapForOrganization($organizationId);

        if ($extensionMap === []) {
            return Call::query()
                ->where('organization_id', $organizationId)
                ->whereNotNull('organization_user_id')
                ->whereIn('status', CallStatus::lostValues())
                ->withRecording()
                ->count();
        }

        $callCount = $this->definedExtensions->apply(
            Call::query()
                ->where('organization_id', $organizationId)
                ->whereIn('status', CallStatus::lostValues()),
            $organizationId,
        )->count();

        $orphanCount = $this->definedExtensions->applyToVoipLogs(
            $this->orphanLogQuery($organizationId, now()->subYears(20), now()->endOfDay())
                ->whereIn('voip_call_logs.status', CallStatus::lostValues()),
            $organizationId,
        )->count();

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
        $from = $from->copy()->timezone(CompanyWorkCalendar::TIMEZONE)->startOfDay()->utc();
        $to = $to->copy()->timezone(CompanyWorkCalendar::TIMEZONE)->endOfDay()->utc();
        $fingerprint = md5(json_encode($this->definedExtensions->matchSetFingerprint($organizationId)) ?: '');
        $cacheKey = $organizationId.'|'.$from->getTimestamp().'|'.$to->getTimestamp().'|'.$fingerprint.'|recorded';

        if (array_key_exists($cacheKey, $this->activityDays)) {
            return $this->activityDays[$cacheKey];
        }

        $days = Cache::remember(
            'extension-activity:'.$cacheKey,
            600,
            fn (): ?array => $this->loadExtensionActivityDayKeys($organizationId, $from, $to),
        );

        return $this->activityDays[$cacheKey] = $days;
    }

    /**
     * @return array<string, true>|null
     */
    private function loadExtensionActivityDayKeys(int $organizationId, CarbonInterface $from, CarbonInterface $to): ?array
    {
        $extensionMap = $this->resolver->extensionEmployeeMapForOrganization($organizationId);

        if ($extensionMap === []) {
            return null;
        }

        $driver = DB::connection()->getDriverName();
        $callDay = CompanyWorkCalendar::sqlDayKey(
            'COALESCE(calls.conversation_date, calls.started_at, calls.created_at)',
            $driver,
        );
        $logDay = CompanyWorkCalendar::sqlDayKey(
            'COALESCE(voip_call_logs.started_at, voip_call_logs.created_at)',
            $driver,
        );

        $callDays = $this->definedExtensions->apply(
            Call::query()
                ->where('calls.organization_id', $organizationId)
                ->occurredBetween($from, $to),
            $organizationId,
        )
            ->selectRaw($callDay.' as day_key')
            ->groupByRaw($callDay)
            ->pluck('day_key');

        $logDays = $this->definedExtensions->applyToVoipLogs(
            $this->orphanLogQuery($organizationId, $from, $to),
            $organizationId,
        )
            ->selectRaw($logDay.' as day_key')
            ->groupByRaw($logDay)
            ->pluck('day_key');

        $days = [];

        foreach ($callDays->merge($logDays) as $dayKey) {
            $normalized = substr((string) $dayKey, 0, 10);

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized) === 1) {
                $days[$normalized] = true;
            }
        }

        return $days;
    }

    /** @return Builder<VoipCallLog> */
    private function orphanLogQuery(int $organizationId, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return VoipCallLog::query()
            ->where('voip_call_logs.organization_id', $organizationId)
            ->occurredBetween($from, $to)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('calls')
                    ->whereColumn('calls.voip_call_log_id', 'voip_call_logs.id');
            });
    }
}
