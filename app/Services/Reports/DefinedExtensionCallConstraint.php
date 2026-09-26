<?php

namespace App\Services\Reports;

use App\Application\Call\Services\CallEmployeeResolver;
use App\Application\Call\Services\UnmatchedVoipExtensionService;
use App\Application\Voip\Support\VoipReportFilter;
use App\Domain\Call\Enums\ConversationSource;
use App\Models\Call;
use App\Models\CallProcessingJob;
use App\Models\OrganizationVoipConnection;
use App\Models\VoipCallLog;
use Illuminate\Database\Eloquent\Builder;

/**
 * Keeps call-volume stats limited to extensions registered in the system.
 * Organizations that have not registered any extension are left unchanged.
 */
class DefinedExtensionCallConstraint
{
    public function __construct(private CallEmployeeResolver $resolver) {}

    /**
     * @param  Builder<Call>  $query
     * @return Builder<Call>
     */
    public function apply(Builder $query, int $organizationId): Builder
    {
        $sets = $this->matchSets($organizationId);

        if ($sets === []) {
            return $query;
        }

        return $query->where(function (Builder $outer) use ($sets): void {
            foreach ($sets as $connectionId => $set) {
                $outer->orWhere(function (Builder $branch) use ($connectionId, $set): void {
                    $this->matchConnection($branch, $connectionId, $set['extensions'], $set['numbers']);
                });
            }
        });
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
        if ($this->matchSets($organizationId) === []) {
            return $query;
        }

        return $query->where(function (Builder $eligible) use ($organizationId): void {
            $eligible->where('source', ConversationSource::ManualUpload->value)
                ->orWhere(function (Builder $matched) use ($organizationId): void {
                    $this->apply($matched, $organizationId);
                });
        });
    }

    /**
     * Queue rows follow the same extension rule as the queue cards.
     *
     * @param  Builder<CallProcessingJob>  $query
     * @return Builder<CallProcessingJob>
     */
    public function applyToProcessingJobs(Builder $query, int $organizationId): Builder
    {
        if ($this->matchSets($organizationId) === []) {
            return $query;
        }

        return $query->whereHas('call', function (Builder $call) use ($organizationId): void {
            $this->applyToQueueCalls($call, $organizationId);
        });
    }

    /**
     * @param  list<string>  $extensions
     * @param  list<string>  $numbers
     * @param  Builder<Call>  $branch
     */
    private function matchConnection(Builder $branch, int $connectionId, array $extensions, array $numbers): void
    {
        $branch->where(function (Builder $side) use ($connectionId, $extensions, $numbers): void {
            $side->whereHas('voipCallLog', function (Builder $log) use ($connectionId, $extensions, $numbers): void {
                $log->where('organization_voip_connection_id', $connectionId)
                    ->where(function (Builder $candidates) use ($extensions, $numbers): void {
                        $candidates->whereIn('destination_number', $numbers)
                            ->orWhereIn('source_number', $numbers);

                        foreach ([...VoipReportFilter::EXTENSION_PAYLOAD_KEYS, 'did'] as $key) {
                            $candidates->orWhereIn('raw_payload->'.$key, $numbers);
                        }

                        foreach ($extensions as $extension) {
                            $this->whereRecordingMentionsExtension($candidates, $extension);
                        }
                    });
            })->orWhere(function (Builder $direct) use ($connectionId, $numbers): void {
                $direct->whereNull('voip_call_log_id')
                    ->where('organization_voip_connection_id', $connectionId)
                    ->where(function (Builder $callNumbers) use ($numbers): void {
                        $callNumbers->whereIn('receiver_number', $numbers)
                            ->orWhereIn('caller_number', $numbers);
                    });
            });
        });
    }

    /** @param  Builder<VoipCallLog>  $query */
    private function whereRecordingMentionsExtension(Builder $query, string $extension): void
    {
        if (! ctype_digit($extension)) {
            return;
        }

        foreach (['-', '.', '/', '_'] as $boundary) {
            $pattern = '%exten-'.$extension.$boundary.'%';
            $query->orWhere('recording_url', 'like', $pattern)
                ->orWhere('raw_payload->recording_url', 'like', $pattern);
        }
    }

    /**
     * @return array<int, array{extensions: list<string>, numbers: list<string>}>
     */
    private function matchSets(int $organizationId): array
    {
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

        return $sets;
    }
}
