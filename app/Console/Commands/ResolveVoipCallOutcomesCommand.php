<?php

namespace App\Console\Commands;

use App\Application\Voip\Jobs\ResolveSimotelCallOutcomeJob;
use App\Domain\Voip\Enums\CallStatus;
use App\Models\VoipCallLog;
use Illuminate\Console\Command;

class ResolveVoipCallOutcomesCommand extends Command
{
    protected $signature = 'voip:resolve-outcomes
        {--connection= : Limit to VoIP connection ID}
        {--organization= : Limit to organization ID}
        {--fix-missed : Re-check missed calls that may have been false timeouts}
        {--older-than=90 : Only ringing calls older than N seconds}
        {--limit=100 : Max call logs to queue}
        {--dry-run : Show what would be queued}';

    protected $description = 'Queue Quick Search resolution for ringing (and optionally false-missed) Simotel calls';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $olderThan = max(0, (int) $this->option('older-than'));
        $fixMissed = (bool) $this->option('fix-missed');
        $dryRun = (bool) $this->option('dry-run');

        $query = VoipCallLog::query()
            ->where('provider_code', 'simotel')
            ->orderBy('id');

        if ($connectionId = $this->option('connection')) {
            $query->where('organization_voip_connection_id', (int) $connectionId);
        }

        if ($organizationId = $this->option('organization')) {
            $query->where('organization_id', (int) $organizationId);
        }

        $query->where(function ($outer) use ($fixMissed, $olderThan): void {
            $outer->where(function ($ringing) use ($olderThan): void {
                $ringing->whereIn('status', [CallStatus::Ringing->value, CallStatus::Initiated->value]);

                if ($olderThan > 0) {
                    $ringing->where('started_at', '<=', now()->subSeconds($olderThan));
                }
            });

            if ($fixMissed) {
                $outer->orWhere(function ($missed): void {
                    $missed->where('status', CallStatus::Missed->value)
                        ->where(function ($payload): void {
                            $payload->where('raw_payload->resolved_by', 'simotel_outcome_timeout')
                                ->orWhereNull('raw_payload->resolved_by')
                                ->orWhereNull('raw_payload');
                        });
                });
            }
        });

        $logs = $query->limit($limit)->get();

        if ($logs->isEmpty()) {
            $this->info('No matching call logs to resolve.');

            return self::SUCCESS;
        }

        $queued = 0;

        foreach ($logs as $log) {
            $wasMissed = $log->status === CallStatus::Missed;
            $this->line(sprintf(
                '  #%d cuid=%s status=%s%s',
                $log->id,
                $log->external_call_id,
                $log->status?->value ?? 'null',
                $wasMissed ? ' (recheck)' : '',
            ));

            if (! $dryRun) {
                // Reset false misses so Quick Search can write the real disposition.
                if ($wasMissed) {
                    $log->update(['status' => CallStatus::Ringing]);
                }

                ResolveSimotelCallOutcomeJob::dispatch(
                    $log->organization_id,
                    $log->organization_voip_connection_id,
                    $log->external_call_id,
                );
            }

            $queued++;
        }

        $this->info($dryRun
            ? "Dry-run: would queue {$queued} call(s)."
            : "Queued {$queued} resolve job(s). Keep queue workers running.");

        return self::SUCCESS;
    }
}
