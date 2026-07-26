<?php

namespace App\Console\Commands;

use App\Application\Voip\Jobs\ProcessVoipWebhookJob;
use App\Application\Voip\Jobs\ResolveSimotelCallOutcomeJob;
use App\Domain\Voip\Enums\CallDirection;
use App\Domain\Voip\Enums\CallStatus;
use App\Models\OrganizationVoipConnection;
use App\Models\VoipCallLog;
use App\Models\VoipWebhookLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ReplayVoipWebhooksCommand extends Command
{
    protected $signature = 'voip:replay-webhooks
        {connection : Organization VoIP connection ID}
        {--mode=outcomes : outcomes (safe historical) | replay (full re-ingest, may popup employees)}
        {--only=incoming : incoming|cdr|unknown|all}
        {--since= : Only logs created at/after this datetime (Y-m-d or Y-m-d H:i:s)}
        {--limit=200 : Max webhook logs to process}
        {--dry-run : Show what would run without dispatching}';

    protected $description = 'Reprocess stored VoIP webhook logs (IncomingCall / CDR) for a connection';

    public function handle(): int
    {
        $connectionId = (int) $this->argument('connection');
        $connection = OrganizationVoipConnection::query()->with('provider')->find($connectionId);

        if (! $connection) {
            $this->error("VoIP connection [{$connectionId}] not found.");

            return self::FAILURE;
        }

        $mode = (string) $this->option('mode');
        $only = (string) $this->option('only');
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $since = $this->option('since');

        if (! in_array($mode, ['outcomes', 'replay'], true)) {
            $this->error('Invalid --mode. Use outcomes or replay.');

            return self::FAILURE;
        }

        $query = VoipWebhookLog::query()
            ->where('organization_voip_connection_id', $connectionId)
            ->whereNotNull('payload')
            ->orderBy('id');

        if (is_string($since) && $since !== '') {
            $query->where('created_at', '>=', Carbon::parse($since));
        }

        $logs = $query->limit($limit)->get()->filter(function (VoipWebhookLog $log) use ($only): bool {
            $eventName = strtolower(trim((string) data_get($log->payload, 'event_name', '')));
            $eventName = preg_replace('/\s+/', '', $eventName) ?? $eventName;

            return match ($only) {
                'incoming' => $eventName === 'incomingcall',
                'cdr' => $eventName === 'cdr',
                'unknown' => ($log->event_type ?? '') === 'unknown' || $eventName === 'incomingcall',
                'all' => true,
                default => false,
            };
        });

        if ($logs->isEmpty()) {
            $this->warn('No matching webhook logs found.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Connection #%d (%s) — mode=%s only=%s — %d log(s)%s',
            $connection->id,
            $connection->name,
            $mode,
            $only,
            $logs->count(),
            $dryRun ? ' [dry-run]' : '',
        ));

        $dispatched = 0;

        foreach ($logs as $log) {
            /** @var array<string, mixed> $payload */
            $payload = is_array($log->payload) ? $log->payload : [];
            $eventName = (string) ($payload['event_name'] ?? '');
            $callId = $this->extractCallId($payload);

            if ($mode === 'replay') {
                $this->line("  replay #{$log->id} {$eventName} cuid=".($callId ?? 'null'));

                if (! $dryRun) {
                    ProcessVoipWebhookJob::dispatch($connectionId, $payload, forceReplay: true);
                }

                $dispatched++;

                continue;
            }

            // outcomes mode: rebuild call logs + Quick Search resolve (no live employee popup storm)
            if ($callId === null || $callId === '') {
                $this->line("  skip #{$log->id} {$eventName} (no cuid)");

                continue;
            }

            $this->line("  outcome #{$log->id} {$eventName} cuid={$callId}");

            if (! $dryRun) {
                $this->ensureRingingCallLog($connection, $payload, $callId);
                ResolveSimotelCallOutcomeJob::dispatch(
                    $connection->organization_id,
                    $connectionId,
                    $callId,
                );
            }

            $dispatched++;
        }

        $this->info($dryRun
            ? "Dry-run complete. Would process {$dispatched} item(s)."
            : "Queued {$dispatched} job(s). Ensure queue workers are running.");

        if ($mode === 'replay') {
            $this->warn('replay mode can open live incoming-call popups for employees.');
        }

        return self::SUCCESS;
    }

    /** @param  array<string, mixed>  $payload */
    private function extractCallId(array $payload): ?string
    {
        foreach (['cuid', 'unique_id', 'uniqueid'] as $key) {
            if (! empty($payload[$key])) {
                return (string) $payload[$key];
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $payload */
    private function ensureRingingCallLog(
        OrganizationVoipConnection $connection,
        array $payload,
        string $callId,
    ): void {
        $existing = VoipCallLog::query()
            ->where('organization_voip_connection_id', $connection->id)
            ->where('external_call_id', $callId)
            ->first();

        if ($existing) {
            return;
        }

        VoipCallLog::query()->create([
            'organization_id' => $connection->organization_id,
            'organization_voip_connection_id' => $connection->id,
            'provider_code' => $connection->provider?->code ?? 'simotel',
            'external_call_id' => $callId,
            'direction' => CallDirection::Inbound,
            'source_number' => (string) ($payload['number'] ?? $payload['src'] ?? 'unknown'),
            'destination_number' => (string) ($payload['entry_point'] ?? $payload['dst'] ?? $payload['did'] ?? ''),
            'status' => CallStatus::Ringing,
            'started_at' => $payload['starttime'] ?? now(),
            'raw_payload' => $payload,
        ]);
    }
}
