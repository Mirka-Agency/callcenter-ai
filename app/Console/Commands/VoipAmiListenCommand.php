<?php

namespace App\Console\Commands;

use App\Application\Voip\Services\AsteriskAmiListener;
use App\Domain\Voip\Enums\VoipIngestionMode;
use App\Models\OrganizationVoipConnection;
use Illuminate\Console\Command;

class VoipAmiListenCommand extends Command
{
    protected $signature = 'voip:ami-listen
                            {--connection= : Listen to a single VoIP connection ID}
                            {--reconnect-delay=5 : Seconds to wait before reconnecting}';

    protected $description = 'Connect to Asterisk AMI and ingest call events for VoIP connections.';

    public function handle(AsteriskAmiListener $listener): int
    {
        if (! config('voip.ami_enabled', false)) {
            $this->error('AMI ingestion is disabled on this deployment (VOIP_AMI_ENABLED=false).');

            return self::FAILURE;
        }

        $connectionId = $this->option('connection');
        $reconnectDelay = max(1, (int) $this->option('reconnect-delay'));

        $query = OrganizationVoipConnection::query()
            ->with('provider')
            ->where('is_active', true)
            ->where('ingestion_mode', VoipIngestionMode::Ami->value);

        if ($connectionId) {
            $query->whereKey((int) $connectionId);
        }

        $connections = $query->get();

        if ($connections->isEmpty()) {
            $this->error('No active AMI VoIP connections found.');

            return self::FAILURE;
        }

        if ($connections->count() > 1) {
            $this->error('Multiple AMI connections found. Pass --connection=ID to listen to one connection per process.');

            return self::FAILURE;
        }

        $connection = $connections->first();
        $this->info("Listening to AMI for connection #{$connection->id} ({$connection->name})...");

        $listener->listen($connection, $reconnectDelay);

        return self::SUCCESS;
    }
}
