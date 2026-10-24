<?php

namespace App\Application\Llm\Prompts;

use App\Domain\Transcription\DTOs\TranscriptionContextData;

final class SonioxContext
{
    /** @return array{general: list<array{key: string, value: string}>} */
    public static function build(?TranscriptionContextData $context = null): array
    {
        $general = [
            ['key' => 'setting', 'value' => 'Telephone conversation'],
            ['key' => 'language', 'value' => 'Primarily Persian, with possible English words and technical terms'],
            ['key' => 'instructions', 'value' => self::instructions()],
        ];

        foreach ([
            'organization' => mb_substr(trim((string) $context?->organizationName), 0, 200),
            'domain' => mb_substr(trim((string) $context?->organizationBusinessContext), 0, 1000),
        ] as $key => $value) {
            if ($value !== '') {
                $general[] = ['key' => $key, 'value' => $value];
            }
        }

        return ['general' => $general];
    }

    public static function instructions(): string
    {
        return <<<'PROMPT'
Transcribe the speech as spoken. Preserve recognizable English words, acronyms, names, and technical terms in their original English spelling when spoken. Do not translate or transliterate them into Persian. Context is background only: do not insert names, terms, or facts that are not audible. Do not guess unclear words or numbers.
PROMPT;
    }
}
