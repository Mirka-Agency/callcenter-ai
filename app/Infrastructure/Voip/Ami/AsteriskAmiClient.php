<?php

namespace App\Infrastructure\Voip\Ami;

use RuntimeException;

class AsteriskAmiClient
{
    /** @var resource|null */
    private $socket = null;

    public function __construct(
        private AsteriskAmiEventParser $parser = new AsteriskAmiEventParser,
    ) {}

    public function connect(string $host, int $port, int $timeoutSeconds = 10): void
    {
        $this->disconnect();

        $socket = @stream_socket_client(
            "tcp://{$host}:{$port}",
            $errno,
            $errstr,
            $timeoutSeconds,
        );

        if ($socket === false) {
            throw new RuntimeException("AMI connect failed: {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, $timeoutSeconds);
        $this->socket = $socket;
    }

    public function login(string $username, string $secret): void
    {
        $this->sendAction([
            'Action' => 'Login',
            'Username' => $username,
            'Secret' => $secret,
            'Events' => 'on',
        ]);

        $response = $this->readResponse();

        if (! $this->parser->isSuccess($response)) {
            throw new RuntimeException('AMI login failed: '.($response['Message'] ?? 'unknown error'));
        }
    }

    public function enableEvents(): void
    {
        $this->sendAction([
            'Action' => 'Events',
            'EventMask' => 'call,cdr,system,agent,user',
        ]);

        $response = $this->readResponse();

        if (! $this->parser->isSuccess($response)) {
            throw new RuntimeException('AMI Events action failed: '.($response['Message'] ?? 'unknown error'));
        }
    }

    /** @return array<string, string>|null */
    public function readEvent(): ?array
    {
        $block = $this->readBlock();

        if ($block === null) {
            return null;
        }

        $event = $this->parser->parseBlock($block);

        return $event === [] ? null : $event;
    }

    /** @return array<string, string> */
    public function readResponse(): array
    {
        $block = $this->readBlock();

        if ($block === null) {
            throw new RuntimeException('AMI connection closed while waiting for response.');
        }

        return $this->parser->parseBlock($block);
    }

    public function disconnect(): void
    {
        if (! is_resource($this->socket)) {
            return;
        }

        @fwrite($this->socket, "Action: Logoff\r\n\r\n");
        @fclose($this->socket);
        $this->socket = null;
    }

    public function isConnected(): bool
    {
        return is_resource($this->socket);
    }

    /** @param array<string, string> $fields */
    public function sendAction(array $fields): void
    {
        if (! is_resource($this->socket)) {
            throw new RuntimeException('AMI socket is not connected.');
        }

        $payload = '';

        foreach ($fields as $key => $value) {
            $payload .= "{$key}: {$value}\r\n";
        }

        $payload .= "\r\n";

        if (@fwrite($this->socket, $payload) === false) {
            throw new RuntimeException('Failed to write AMI action.');
        }
    }

    private function readBlock(): ?string
    {
        if (! is_resource($this->socket)) {
            return null;
        }

        $lines = [];

        while (($line = fgets($this->socket)) !== false) {
            $line = rtrim($line, "\r\n");

            if ($line === '') {
                break;
            }

            $lines[] = $line;
        }

        if ($lines === [] && feof($this->socket)) {
            return null;
        }

        return implode("\n", $lines);
    }
}
