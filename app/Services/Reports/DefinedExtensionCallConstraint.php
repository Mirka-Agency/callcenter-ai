<?php

namespace App\Services\Reports;

use App\Application\Call\Services\CallEmployeeResolver;
use App\Application\Call\Services\UnmatchedVoipExtensionService;
use App\Application\Voip\Support\VoipReportFilter;
use App\Domain\Call\Enums\ConversationSource;
use App\Models\Call;
use App\Models\CallProcessingJob;
use App\Models\Organization;
use App\Models\OrganizationVoipConnection;
use App\Models\VoipCallLog;
use Illuminate\Database\Eloquent\Builder;

/**
 * Keeps call-volume stats limited to extensions registered in the system.
 * Organizations that have not registered any extension are left unchanged.
 */
class DefinedExtensionCallConstraint
{
    /** @var array<int, array<int, array{extensions: list<string>, numbers: list<string>}>> */
    private array $matchSetCache = [];

    public function __construct(private CallEmployeeResolver $resolver) {}

    /**
     * @param  Builder<Call>  $query
     * @return Builder<Call>
     */
    public function apply(Builder $query, int $organizationId): Builder
    {
        $query = $query->withRecording();
        $sets = $this->matchSets($organizationId);

        if ($sets === []) {
            return $query;
        }

        return $query->where($query->getModel()->getTable().'.counts_for_extension_reports', true);
    }

    /**
     * Calls that belong on the employer queue cards.
     * A manual upload stays included because it is not a PBX call on an unknown extension.
     *
     * @param  Builder<Call>  $query
     * @return Builder<Call>
     */
    public function applyToQueueCalls(Builder $query, int $organizationId): Builder
    {
        $query = $query->withRecording();

        if ($this->matchSets($organizationId) === []) {
            return $query;
        }

        return $query->where(function (Builder $eligible): void {
            $eligible->where('source', ConversationSource::ManualUpload->value)
                ->orWhere('counts_for_extension_reports', true);
        });
    }

    /**
     * VoIP calls on extensions that are not registered for the organization.
     * These are ingested but intentionally skipped from analysis and report totals.
     * When the organization has no registered extensions, nothing is "outside".
     *
     * @param  Builder<Call>  $query
     * @return Builder<Call>
     */
    public function applyOutsideAnalysis(Builder $query, int $organizationId): Builder
    {
        $query = $query->withRecording()
            ->where($query->getModel()->getTable().'.source', ConversationSource::Voip->value);

        if ($this->matchSets($organizationId) === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where($query->getModel()->getTable().'.counts_for_extension_reports', false);
    }

    /**
     * Same rule as the report filter, stored on the call so page loads do not scan recordings.
     */
    public function countsForReports(Call $call): bool
    {
        $sets = $this->matchSets((int) $call->organization_id);

        if ($sets === []) {
            return false;
        }

        $call->loadMissing('voipCallLog');

        foreach ($sets as $connectionId => $set) {
            if ($this->callMatchesConnection($call, (int) $connectionId, $set['extensions'], $set['numbers'])) {
                return true;
            }
        }

        return false;
    }

    public function refreshOrganization(int $organizationId): void
    {
        unset($this->matchSetCache[$organizationId]);
        $this->resolver->forgetExtensionMap($organizationId);

        Call::query()
            ->where('organization_id', $organizationId)
            ->with('voipCallLog')
            ->orderBy('id')
            ->chunkById(300, function ($calls): void {
                foreach ($calls as $call) {
                    $counts = $this->countsForReports($call);

                    if ((bool) $call->counts_for_extension_reports === $counts) {
                        continue;
                    }

                    Call::query()->whereKey($call->id)->update([
                        'counts_for_extension_reports' => $counts,
                    ]);
                }
            });
    }

    public function refreshAll(): void
    {
        Organization::query()->orderBy('id')->pluck('id')->each(function ($organizationId): void {
            $this->refreshOrganization((int) $organizationId);
        });
    }

    /**
     * Queue rows follow the same rules as the queue cards:
     * a defined extension, and a recording on the call.
     *
     * @param  Builder<CallProcessingJob>  $query
     * @return Builder<CallProcessingJob>
     */
    public function applyToProcessingJobs(Builder $query, int $organizationId): Builder
    {
        return $query->whereHas('call', function (Builder $call) use ($organizationId): void {
            $this->applyToQueueCalls($call, $organizationId);
        });
    }

    /**
     * Orphan VoIP logs have no call row, so they cannot use the stored flag.
     * This stays off the main call scan.
     *
     * @param  Builder<VoipCallLog>  $query
     * @return Builder<VoipCallLog>
     */
    public function applyToVoipLogs(Builder $query, int $organizationId): Builder
    {
        $query->whereNotNull('voip_call_logs.recording_url')
            ->where('voip_call_logs.recording_url', '!=', '');

        $sets = $this->matchSets($organizationId);

        if ($sets === []) {
            return $query;
        }

        return $query->where(function (Builder $outer) use ($sets): void {
            foreach ($sets as $connectionId => $set) {
                $outer->orWhere(function (Builder $log) use ($connectionId, $set): void {
                    $log->where('voip_call_logs.organization_voip_connection_id', $connectionId)
                        ->where(function (Builder $candidates) use ($set): void {
                            $candidates->whereIn('voip_call_logs.destination_number', $set['numbers'])
                                ->orWhereIn('voip_call_logs.source_number', $set['numbers']);

                            foreach ([...VoipReportFilter::EXTENSION_PAYLOAD_KEYS, 'did'] as $key) {
                                $candidates->orWhereIn('voip_call_logs.raw_payload->'.$key, $set['numbers']);
                            }

                            foreach ($set['extensions'] as $extension) {
                                if (! ctype_digit($extension)) {
                                    continue;
                                }

                                foreach (['-', '.', '/', '_'] as $boundary) {
                                    $pattern = '%exten-'.$extension.$boundary.'%';
                                    $candidates->orWhere('voip_call_logs.recording_url', 'like', $pattern)
                                        ->orWhere('voip_call_logs.raw_payload->recording_url', 'like', $pattern);
                                }
                            }
                        });
                });
            }
        });
    }

    /**
     * @param  list<string>  $extensions
     * @param  list<string>  $numbers
     */
    private function callMatchesConnection(Call $call, int $connectionId, array $extensions, array $numbers): bool
    {
        $log = $call->voipCallLog;

        if ($log !== null) {
            return (int) $log->organization_voip_connection_id === $connectionId
                && $this->logMatchesExtension($log, $extensions, $numbers);
        }

        return (int) $call->organization_voip_connection_id === $connectionId
            && (
                $this->numberMatches($call->receiver_number, $numbers)
                || $this->numberMatches($call->caller_number, $numbers)
            );
    }

    /**
     * @param  list<string>  $extensions
     * @param  list<string>  $numbers
     */
    private function logMatchesExtension(VoipCallLog $log, array $extensions, array $numbers): bool
    {
        if ($this->numberMatches($log->destination_number, $numbers) || $this->numberMatches($log->source_number, $numbers)) {
            return true;
        }

        $payload = is_array($log->raw_payload) ? $log->raw_payload : [];

        foreach ([...VoipReportFilter::EXTENSION_PAYLOAD_KEYS, 'did'] as $key) {
            if ($this->numberMatches($payload[$key] ?? null, $numbers)) {
                return true;
            }
        }

        $url = (string) ($log->recording_url ?? $payload['recording_url'] ?? '');

        foreach ($extensions as $extension) {
            if (! ctype_digit($extension)) {
                continue;
            }

            foreach (['-', '.', '/', '_'] as $boundary) {
                if (str_contains($url, 'exten-'.$extension.$boundary)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $numbers
     */
    private function numberMatches(mixed $value, array $numbers): bool
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return false;
        }

        return in_array(trim((string) $value), $numbers, true);
    }

    /**
     * Stable description of the extensions that count toward reports.
     * Used so cached call totals refresh when an extension is added or removed.
     *
     * @return array<int, array{extensions: list<string>, numbers: list<string>}>
     */
    public function matchSetFingerprint(int $organizationId): array
    {
        return $this->matchSets($organizationId);
    }

    /**
     * @return array<int, array{extensions: list<string>, numbers: list<string>}>
     */
    private function matchSets(int $organizationId): array
    {
        if (array_key_exists($organizationId, $this->matchSetCache)) {
            return $this->matchSetCache[$organizationId];
        }

        $byConnection = [];

        foreach (array_keys($this->resolver->extensionEmployeeMapForOrganization($organizationId)) as $key) {
            [$connectionId, $extension] = explode('|', (string) $key, 2);
            $extension = UnmatchedVoipExtensionService::normalizeExtension($extension);

            if ($extension === '') {
                continue;
            }

            $byConnection[(int) $connectionId][$extension] = $extension;
        }

        if ($byConnection === []) {
            return [];
        }

        $connections = OrganizationVoipConnection::query()
            ->whereIn('id', array_keys($byConnection))
            ->get(['id', 'settings']);

        $sets = [];

        foreach ($byConnection as $connectionId => $extensions) {
            $extensions = array_values($extensions);
            $numbers = $extensions;
            $settings = $connections->firstWhere('id', $connectionId)?->settings;
            $mapping = is_array($settings['extension_mapping'] ?? null) ? $settings['extension_mapping'] : [];

            foreach ($mapping as $from => $to) {
                $alias = trim((string) $from);
                $target = UnmatchedVoipExtensionService::normalizeExtension((string) $to);

                if ($alias !== '' && in_array($target, $extensions, true)) {
                    $numbers[] = $alias;
                }
            }

            $sets[$connectionId] = [
                'extensions' => $extensions,
                'numbers' => array_values(array_unique($numbers)),
            ];
        }

        return $this->matchSetCache[$organizationId] = $sets;
    }
}
