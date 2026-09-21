<?php

namespace App\Domain\Llm\Exceptions;

class LlmRemoteDisabledException extends LlmAnalysisException
{
    public const MESSAGE = 'تحلیل هوش مصنوعی در این محیط غیرفعال است و درخواستی به AvalAI ارسال نشد.';

    public static function disabled(): self
    {
        return new self(self::MESSAGE);
    }

    public static function forHost(string $host): self
    {
        return new self(self::MESSAGE." ({$host})");
    }
}
