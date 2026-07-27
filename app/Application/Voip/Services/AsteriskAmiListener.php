<?php

namespace App\Application\Voip\Services;

use App\Infrastructure\Voip\Ami\AsteriskAmiClient;
use App\Models\OrganizationVoipConnection;
use Illuminate\Support\Facades\Log;
use Throwable;

class AsteriskAmiListener
{
    public function __construct(
        private AsteriskAmiClient $client,
        private AsteriskAmiCallTracker $tracker,
        private AsteriskAmiIngestionBridge $bridge,
    ) {}

    public function listen(OrganizationVoipConnection $connection, int $reconnectDelaySeconds = 5): void
    {
        $config = $this->resolveAmiConfig($connection);

        while (true) {
            try {
                $this->connectAndProcess($connection, $config);
            } catch (Throwable $exception) {
                Log::error('ami_listener_error', [
                    'connection_id' => $connection->id,
                    'organization_id' => $connection->organization_id,
                    'message' => $exception->getMessage(),
                ]);
            } finally {
                $this->client->disconnect();
            }

            sleep($reconnectDelaySeconds);
        }
    }

    /** @param array{host: string, port: int, username: string, password: string} $config */
    private function connectAndProcess(OrganizationVoipConnection $connection, array $config): void
    {
        $this->client->connect($config['host'], $config['port']);
        $this->client->login($config['username'], $config['password']);
        $this->client->enableEvents();

        Log::info('ami_listener_connected', [
            'connection_id' => $connection->id,
            'host' => $config['host'],
            'port' => $config['port'],
            'username' => $config['username'],
        ]);

        while ($this->client->isConnected()) {
            $event = $this->client->readEvent();

            if ($event === null) {
                break;
            }

            if (isset($event['Response'])) {
                continue;
            }

            $payload = $this->tracker->handle($event);

            if ($payload !== null) {
                $this->bridge->dispatch($connection, $payload);

                Log::info('ami_call_ingested', [
                    'connection_id' => $connection->id,
                    'call_id' => $payload['call_id'] ?? null,
                    'event' => $event['Event'] ?? null,
                ]);
            }
        }
    }

    /** @return array{host: string, port: int, username: string, password: string} */
    public function resolveAmiConfig(OrganizationVoipConnection $connection): array
    {
        $settings = $connection->settings ?? [];
        $extra = $settings['extra']['ami'] ?? [];
        $credentials = $connection->credentials ?? [];

        $host = trim((string) ($extra['host'] ?? ''));
        $port = (int) ($extra['port'] ?? 5038);
        $username = trim((string) ($credentials['ami_username'] ?? $credentials['username'] ?? ''));
        $password = (string) ($credentials['ami_password'] ?? $credentials['password'] ?? '');

        if ($host === '' || $username === '' || $password === '') {
            throw new \InvalidArgumentException('AMI host, username, and password are required for connection '.$connection->id);
        }

        return compact('host', 'port', 'username', 'password');
    }
}
