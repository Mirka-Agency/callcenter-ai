<?php

namespace App\Domain\Llm\Exceptions;

class LlmTransientException extends LlmAnalysisException
{
    public static function fromProviderError(string $error): self
    {
        return new self($error);
    }

    public static function isTransientMessage(string $message): bool
    {
        $needles = [
            'service_unavailable',
            'rate_limit_exceeded',
            'rate_limit',
            'server_error',
            'temporarily unavailable',
            'try again later',
            '"code": 503',
            '"code":503',
            '"code": 429',
            '"code":429',
            '"code": 502',
            '"code":502',
            '"code": 504',
            '"code":504',
        ];

        $normalized = strtolower($message);

        foreach ($needles as $needle) {
            if (str_contains($normalized, strtolower($needle))) {
                return true;
            }
        }

        return false;
    }
}
