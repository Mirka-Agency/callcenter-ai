<?php

namespace App\Services\Reports;

use App\Application\Call\Services\CallEmployeeResolver;
use App\Domain\Voip\Enums\CallStatus;
use App\Enums\ReportDatePreset;
use App\Models\Call;
use App\Models\VoipCallLog;
use App\Services\CallIntake\CallIntakePolicy;
use App\Services\CallIntake\CallIntakeSettings;
use App\Services\CallIntake\Filters\UnassignedAgentCallsFilter;
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
        [$from, $to] = ReportDatePreset::Today->resolve();
        $dayKey = now(CompanyWorkCalendar::TIMEZONE)->toDateString();

        return Cache::remember(
            $this->todayCacheKey($organizationId, $dayKey),
            60,
            fn (): int => $this->countBetween(
                $organizationId,
                $from,
                $to,
            ),
        );
    }

    public function forgetToday(int $organizationId, ?string $token = null): void
    {
        $dayKey = now(CompanyWorkCalendar::TIMEZONE)->toDateString();
        Cache::forget($this->todayCacheKey($organizationId, $dayKey, $token));
        Cache::forget('calls-today:'.$organizationId.':'.$dayKey.':recorded-tehran-v1');
        Cache::forget('calls-today:'.$organizationId.':'.$dayKey.':pbx-intake-v1');
    }

    public function countThisMonth(int $organizationId): int
    {
        [$from, $to] = ReportDatePreset::ThisMonth->resolve();

        return $this->countBetween(
            $organizationId,
            $from,
            $to,
        );
    }

    /**
     * Same volume definition as the analysis list "تعداد کل تماس‌ها" card:
     * intake-filtered PBX voip logs in the window, plus standalone calls that never got a voip log
     * (manual upload / import).
     */
    public function countBetween(int $organizationId, Carbon $from, Carbon $to): int
    {
        $from = $from->copy();
        $to = $to->copy();

        $pbx = app(CallIntakePolicy::class)->applyToVoipLogs(
            VoipCallLog::query()
                ->where('voip_call_logs.organization_id', $organizationId)
                ->occurredBetween($from, $to),
            $organizationId,
        )->count();

        $standalone = app(CallIntakePolicy::class)->applyToCalls(
            Call::query()
                ->where('organization_id', $organizationId)
                ->whereNull('voip_call_log_id')
                ->occurredBetween($from, $to),
            $organizationId,
        )->count();

        return $pbx + $standalone;
    }

    public function countLost(int $organizationId): int
    {
        $extensionMap = $this->resolver->extensionEmployeeMapForOrganization($organizationId);

        if ($extensionMap === []) {
            $query = Call::query()
                ->where('organization_id', $organizationId)
                ->whereIn('status', CallStatus::lostValues())
                ->withRecording();

            if (! $this->includesUnassigned($organizationId)) {
                $query->whereNotNull('organization_user_id');
            }

            return app(CallIntakePolicy::class)->applyToCalls($query, $organizationId)->count();
        }

        $callCount = $this->definedExtensions->apply(
            Call::query()
                ->where('organization_id', $organizationId)
                ->whereIn('status', CallStatus::lostValues()),
            $organizationId,
        )->count();

        $orphanCount = app(CallIntakePolicy::class)->applyToVoipLogs(
            $this->definedExtensions->applyToVoipLogs(
                $this->orphanLogQuery($organizationId, now()->subYears(20), now()->endOfDay())
                    ->whereIn('voip_call_logs.status', CallStatus::lostValues()),
                $organizationId,
            ),
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
        $intake = app(CallIntakeSettings::class)->cacheToken($organizationId);
        $cacheKey = $organizationId.'|'.$from->getTimestamp().'|'.$to->getTimestamp().'|'.$fingerprint.'|'.$intake.'|recorded';

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

        $logDays = app(CallIntakePolicy::class)->applyToVoipLogs(
            $this->definedExtensions->applyToVoipLogs(
                $this->orphanLogQuery($organizationId, $from, $to),
                $organizationId,
            ),
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

    private function includesUnassigned(int $organizationId): bool
    {
        return app(CallIntakeSettings::class)->enabled($organizationId, UnassignedAgentCallsFilter::KEY);
    }

    private function todayCacheKey(int $organizationId, string $dayKey, ?string $token = null): string
    {
        $token ??= app(CallIntakeSettings::class)->cacheToken($organizationId);

        return 'calls-today:'.$organizationId.':'.$dayKey.':'.$token.':pbx-intake-v1';
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
