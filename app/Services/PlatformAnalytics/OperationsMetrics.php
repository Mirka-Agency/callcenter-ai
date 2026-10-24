<?php

namespace App\Services\PlatformAnalytics;

use App\Domain\Call\Enums\CallProcessingStatus;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\LlmAnalysisLog;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OperationsMetrics
{
    public const FAILING_RATE = 0.2;

    /**
     * Error rate per integration provider. A high rate across many organizations
     * points to a provider-side outage rather than one customer's setup.
     *
     * @return list<array{kind: string, channel: string, provider: string, total: int, failed: int, failure_rate: float|null, organizations: int, failing_organizations: int, last_failure_at: string|null}>
     */
    public function providerHealth(CarbonInterface $since): array
    {
        $sources = [
            ['voip', 'webhook', 'voip_webhook_logs', 'organization_voip_connection_id', 'organization_voip_connections', 'voip_provider_id', 'voip_providers'],
            ['voip', 'sync', 'voip_sync_logs', 'organization_voip_connection_id', 'organization_voip_connections', 'voip_provider_id', 'voip_providers'],
            ['crm', 'sync', 'crm_sync_logs', 'organization_crm_connection_id', 'organization_crm_connections', 'crm_provider_id', 'crm_providers'],
        ];

        $rows = [];

        foreach ($sources as [$kind, $channel, $logs, $connectionKey, $connections, $providerKey, $providers]) {
            $result = DB::table($logs)
                ->join($connections, "{$connections}.id", '=', "{$logs}.{$connectionKey}")
                ->join($providers, "{$providers}.id", '=', "{$connections}.{$providerKey}")
                ->where("{$logs}.created_at", '>=', $since)
                ->groupBy("{$providers}.id", "{$providers}.name")
                ->selectRaw("{$providers}.name as provider")
                ->selectRaw('COUNT(*) as total')
                ->selectRaw("SUM(CASE WHEN {$logs}.status = 'failed' THEN 1 ELSE 0 END) as failed")
                ->selectRaw("COUNT(DISTINCT {$connections}.organization_id) as organizations")
                ->selectRaw("COUNT(DISTINCT CASE WHEN {$logs}.status = 'failed' THEN {$connections}.organization_id END) as failing_organizations")
                ->selectRaw("MAX(CASE WHEN {$logs}.status = 'failed' THEN {$logs}.created_at END) as last_failure_at")
                ->get();

            foreach ($result as $row) {
                $total = (int) $row->total;
                $failed = (int) $row->failed;

                $rows[] = [
                    'kind' => $kind,
                    'channel' => $channel,
                    'provider' => (string) $row->provider,
                    'total' => $total,
                    'failed' => $failed,
                    'failure_rate' => $total === 0 ? null : $failed / $total,
                    'organizations' => (int) $row->organizations,
                    'failing_organizations' => (int) $row->failing_organizations,
                    'last_failure_at' => $row->last_failure_at,
                ];
            }
        }

        usort($rows, fn (array $a, array $b): int => ($b['failure_rate'] ?? 0) <=> ($a['failure_rate'] ?? 0));

        return $rows;
    }

    /** @return list<array{kind: string, channel: string, provider: string, total: int, failed: int, failure_rate: float|null, organizations: int, failing_organizations: int, last_failure_at: string|null}> */
    public function failingProviders(CarbonInterface $since, int $minimumEvents = 10): array
    {
        return array_values(array_filter(
            $this->providerHealth($since),
            fn (array $row): bool => $row['total'] >= $minimumEvents && ($row['failure_rate'] ?? 0) >= self::FAILING_RATE,
        ));
    }

    /**
     * Analysis volume, latency and token use per model and prompt version.
     *
     * @return list<array{model: string, prompt_version: string|null, analyses: int, avg_duration_ms: float|null, p95_duration_ms: int|null, avg_tokens: float|null, avg_score: float|null, not_evaluable_rate: float|null}>
     */
    public function aiQuality(CarbonInterface $since): array
    {
        $groups = ConversationAnalysis::query()
            ->where('analyzed_at', '>=', $since)
            ->groupBy('model_name', 'prompt_version')
            ->selectRaw('model_name, prompt_version, COUNT(*) as analyses')
            ->selectRaw('AVG(processing_duration_ms) as avg_duration_ms')
            ->selectRaw('AVG(total_tokens) as avg_tokens')
            ->selectRaw('AVG(CASE WHEN is_evaluable OR is_evaluable IS NULL THEN score END) as avg_score')
            ->selectRaw('SUM(CASE WHEN NOT is_evaluable THEN 1 ELSE 0 END) as not_evaluable')
            ->orderByDesc('analyses')
            ->toBase()
            ->get();

        return $groups->map(function (object $group) use ($since): array {
            $analyses = (int) $group->analyses;

            $durations = ConversationAnalysis::query()
                ->where('analyzed_at', '>=', $since)
                ->where('model_name', $group->model_name)
                ->when(
                    $group->prompt_version === null,
                    fn ($query) => $query->whereNull('prompt_version'),
                    fn ($query) => $query->where('prompt_version', $group->prompt_version),
                )
                ->where('processing_duration_ms', '>', 0)
                ->orderBy('processing_duration_ms')
                ->pluck('processing_duration_ms')
                ->all();

            return [
                'model' => (string) $group->model_name,
                'prompt_version' => $group->prompt_version,
                'analyses' => $analyses,
                'avg_duration_ms' => $group->avg_duration_ms === null ? null : (float) $group->avg_duration_ms,
                'p95_duration_ms' => $durations === [] ? null : (int) $durations[(int) floor(0.95 * (count($durations) - 1))],
                'avg_tokens' => $group->avg_tokens === null ? null : (float) $group->avg_tokens,
                'avg_score' => $group->avg_score === null ? null : (float) $group->avg_score,
                'not_evaluable_rate' => $analyses === 0 ? null : (int) $group->not_evaluable / $analyses,
            ];
        })->all();
    }

    /** @return array{total: int, failed: int, failure_rate: float|null} */
    public function llmRequests(CarbonInterface $since): array
    {
        $counts = LlmAnalysisLog::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $total = (int) $counts->sum();
        $failed = (int) ($counts['failed'] ?? 0);

        return ['total' => $total, 'failed' => $failed, 'failure_rate' => $total === 0 ? null : $failed / $total];
    }

    /**
     * Most common processing errors, so support can spot one root cause behind many failures.
     *
     * @return Collection<int, object{processing_error: string, failures: int, organizations: int}>
     */
    public function topProcessingErrors(CarbonInterface $since, int $limit = 8): Collection
    {
        return Call::query()
            ->where('processing_status', CallProcessingStatus::Failed)
            ->where('updated_at', '>=', $since)
            ->whereNotNull('processing_error')
            ->groupBy('processing_error')
            ->selectRaw('processing_error, COUNT(*) as failures, COUNT(DISTINCT organization_id) as organizations')
            ->orderByDesc('failures')
            ->limit($limit)
            ->toBase()
            ->get();
    }

    /** Calls sitting in an in-progress stage for longer than expected. */
    public function stuckCalls(int $olderThanMinutes = 60): int
    {
        return Call::query()
            ->whereIn('processing_status', [
                CallProcessingStatus::Pending,
                CallProcessingStatus::Downloading,
                CallProcessingStatus::Transcribing,
                CallProcessingStatus::Analyzing,
            ])
            ->where('updated_at', '<', now()->subMinutes($olderThanMinutes))
            ->count();
    }
}
