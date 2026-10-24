<?php

namespace App\Application\Transcription\Services;

class SonioxTranscriptNormalizer
{
    /**
     * @param  list<array<string, mixed>>  $tokens
     * @return list<array{speaker: ?string, text: string, start_ms: ?int, end_ms: ?int, tokens: array}>
     */
    public function segments(array $tokens): array
    {
        $segments = [];
        foreach ($tokens as $token) {
            $text = $token['text'] ?? '';
            if (! is_string($text) || $text === '' || preg_match('/^<[^>]+>$/u', $text)) {
                continue;
            }
            $speaker = isset($token['speaker']) ? (string) $token['speaker'] : null;
            $last = count($segments) - 1;
            if ($last < 0 || $segments[$last]['speaker'] !== $speaker) {
                $segments[] = [
                    'speaker' => $speaker,
                    'text' => '',
                    'start_ms' => isset($token['start_ms']) ? (int) $token['start_ms'] : null,
                    'end_ms' => null,
                    'tokens' => [],
                ];
                $last++;
            }
            $segments[$last]['text'] .= $text;
            $segments[$last]['end_ms'] = isset($token['end_ms']) ? (int) $token['end_ms'] : $segments[$last]['end_ms'];
            $segments[$last]['tokens'][] = $token;
        }

        return $segments;
    }

    /** @param list<array<string, mixed>> $segments */
    public function render(array $segments, array $roles = []): string
    {
        return implode("\n", array_map(function (array $segment) use ($roles): string {
            $speaker = $segment['speaker'];
            $label = match ($roles[$speaker ?? ''] ?? null) {
                'agent' => 'کارشناس',
                'customer' => 'مشتری',
                default => $speaker === null ? 'گوینده نامشخص' : 'گوینده '.$speaker,
            };

            return $label.': '.trim($segment['text']);
        }, $segments));
    }
}
