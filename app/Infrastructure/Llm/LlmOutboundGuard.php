<?php

namespace App\Infrastructure\Llm;

use App\Domain\Llm\Exceptions\LlmRemoteDisabledException;

class LlmOutboundGuard
{
    public function remoteAnalysisEnabled(): bool
    {
        return (bool) config('llm.remote_enabled');
    }

    public function shouldBlockOutboundHosts(): bool
    {
        return (bool) config('llm.block_avalai') || ! $this->remoteAnalysisEnabled();
    }

    public function disabledMessage(): string
    {
        return LlmRemoteDisabledException::MESSAGE;
    }

    public function assertRemoteAnalysisEnabled(): void
    {
        if (! $this->remoteAnalysisEnabled()) {
            throw LlmRemoteDisabledException::disabled();
        }
    }

    public function assertUrlAllowed(string $url): void
    {
        if (! $this->shouldBlockOutboundHosts()) {
            return;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '' || ! $this->hostIsBlocked($host)) {
            return;
        }

        throw LlmRemoteDisabledException::forHost($host);
    }

    private function hostIsBlocked(string $host): bool
    {
        foreach ($this->blockedHosts() as $blocked) {
            if ($host === $blocked || str_ends_with($host, '.'.$blocked)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function blockedHosts(): array
    {
        $hosts = config('llm.blocked_hosts', []);

        if (! is_array($hosts)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $host): string => strtolower(trim((string) $host)),
            $hosts,
        )));
    }
}
