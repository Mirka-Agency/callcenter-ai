<?php

namespace App\Console\Commands;

use App\Application\Voip\Services\VoipConnectionResolver;
use App\Infrastructure\Voip\Adapters\SimotelVoipAdapter;
use App\Models\OrganizationVoipConnection;
use Illuminate\Console\Command;

class DiagnoseSimotelVoipCommand extends Command
{
    protected $signature = 'voip:diagnose-simotel
        {connection : Organization VoIP connection ID}
        {--cuid= : Optional cuid to look up via quick/search}';

    protected $description = 'Probe Simotel API credentials/permissions (quick/search) for a VoIP connection';

    public function handle(VoipConnectionResolver $resolver): int
    {
        $connectionId = (int) $this->argument('connection');
        $connection = OrganizationVoipConnection::query()->with('provider')->find($connectionId);

        if (! $connection) {
            $this->error("VoIP connection [{$connectionId}] not found.");

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Connection #%d (%s) org=%d provider=%s',
            $connection->id,
            $connection->name,
            $connection->organization_id,
            $connection->provider?->code ?? 'n/a',
        ));

        $credentials = $connection->credentials ?? [];
        $this->line('api_url='.($credentials['api_url'] ?? '(empty)'));
        $this->line('has_api_key='.(filled($credentials['api_key'] ?? null) ? 'yes' : 'no'));
        $this->line('has_username='.(filled($credentials['username'] ?? null) ? 'yes' : 'no'));
        $this->line('has_password='.(filled($credentials['password'] ?? null) ? 'yes' : 'no'));

        try {
            [$config, $adapter] = $resolver->resolve($connection->organization_id, $connection->id);
        } catch (\Throwable $e) {
            $this->error('Resolver failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! $adapter instanceof SimotelVoipAdapter) {
            $this->error('Adapter is not SimotelVoipAdapter.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Testing reports/quick/search …');
        $test = $adapter->testConnection();

        if ($test->success) {
            $this->info('OK: '.$test->message);
        } else {
            $this->error('FAIL: '.($test->error ?? 'unknown'));
            if ($adapter->isAccessDeniedResult($test)) {
                $this->warn('Action required in Simotel panel:');
                $this->warn('  1) API user → enable permission: reports/quick (and reports/audio if needed)');
                $this->warn('  2) Prefer Basic Auth username/password + X-APIKEY together (official docs)');
                $this->warn('  3) Ensure server IP is allowed (or Allow All IPs)');
                $this->warn('Until fixed: rely on CDR webhooks for ANSWERED / NO ANSWER — do not use API outcome resolve.');
            }
        }

        $cuid = $this->option('cuid');
        if (is_string($cuid) && $cuid !== '') {
            $this->newLine();
            $this->info("Looking up cuid={$cuid} …");
            $details = $adapter->getCallDetailsWithContext($cuid);
            if ($details->success) {
                $rows = $details->data['rows'] ?? [];
                $this->info('OK rows='.count(is_array($rows) ? $rows : []));
                if (is_array($rows) && $rows !== []) {
                    $row = $rows[0];
                    $this->line(json_encode([
                        'disposition' => $row['disposition'] ?? null,
                        'src' => $row['src'] ?? null,
                        'dst' => $row['dst'] ?? null,
                        'billsec' => $row['billsec'] ?? null,
                        'record' => $row['record'] ?? null,
                    ], JSON_UNESCAPED_UNICODE));
                }
            } else {
                $this->error('Lookup failed: '.($details->error ?? 'unknown'));
            }
        }

        return $test->success ? self::SUCCESS : self::FAILURE;
    }
}
