<?php

namespace App\Application\Llm\Services;

/**
 * Drops ringtone, hold music, and other non-speech that speech models turn into words.
 */
class TranscriptSanitizer
{
    private const NO_SPEECH_PROB = 0.6;

    private const MIN_AVG_LOGPROB = -1.0;

    private const MAX_COMPRESSION_RATIO = 2.4;

    private const LOW_CONFIDENCE_WORD_RUN = 4;

    private const HALLUCINATED_REPEAT_MIN_CHARS = 20;

    private const HALLUCINATED_REPEAT_MIN_COUNT = 3;

    public function clean(string $transcript): string
    {
        $lines = preg_split('/\R/u', TranscriptionPrompt::unwrap($transcript)) ?: [];
        $kept = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $this->isNonSpeech($line)) {
                continue;
            }

            $kept[] = $line;
        }

        return trim(implode("\n", $this->dropRepeatedHallucinations($kept)));
    }

    /**
     * Whisper marks ringtone and music with no_speech_prob, avg_logprob, and compression_ratio.
     *
     * @param  list<array<string, mixed>>  $segments
     * @return list<array<string, mixed>>
     */
    public function reliableSegments(array $segments): array
    {
        $kept = [];

        foreach ($segments as $segment) {
            if (! is_array($segment) || ! $this->segmentIsSpeech($segment)) {
                continue;
            }

            $kept[] = $segment;
        }

        return $kept;
    }

    /**
     * gpt-4o-transcribe can return a token logprob for each piece of the transcript.
     * A long run of very uncertain tokens is the usual shape of ringtone or music.
     *
     * @param  list<array<string, mixed>>  $logprobs
     */
    public function dropLowConfidenceSpans(string $text, array $logprobs): string
    {
        if ($text === '' || $logprobs === []) {
            return $text;
        }

        $words = [];
        $current = '';
        $sum = 0.0;
        $count = 0;

        foreach ($logprobs as $item) {
            if (! is_array($item) || ! is_string($item['token'] ?? null) || ! is_numeric($item['logprob'] ?? null)) {
                return $text;
            }

            $token = $item['token'];

            if ($current !== '' && preg_match('/^\s/u', $token) === 1) {
                $words[] = ['text' => $current, 'avg' => $sum / $count];
                $current = '';
                $sum = 0.0;
                $count = 0;
            }

            $current .= $token;
            $sum += (float) $item['logprob'];
            $count++;
        }

        if ($count > 0) {
            $words[] = ['text' => $current, 'avg' => $sum / $count];
        }

        $rebuilt = implode('', array_column($words, 'text'));
        if ($this->compact($rebuilt) !== $this->compact($text)) {
            return $text;
        }

        $kept = [];
        $total = count($words);
        $index = 0;

        while ($index < $total) {
            if ($words[$index]['avg'] > self::MIN_AVG_LOGPROB) {
                $kept[] = $words[$index]['text'];
                $index++;

                continue;
            }

            $end = $index;
            while ($end < $total && $words[$end]['avg'] <= self::MIN_AVG_LOGPROB) {
                $end++;
            }

            if ($end - $index < self::LOW_CONFIDENCE_WORD_RUN) {
                for ($cursor = $index; $cursor < $end; $cursor++) {
                    $kept[] = $words[$cursor]['text'];
                }
            }

            $index = $end;
        }

        return trim(implode('', $kept));
    }

    /** @param  array<string, mixed>  $segment */
    private function segmentIsSpeech(array $segment): bool
    {
        if (trim((string) ($segment['text'] ?? '')) === '') {
            return false;
        }

        $compression = $this->metric($segment, 'compression_ratio');
        if ($compression !== null && $compression >= self::MAX_COMPRESSION_RATIO) {
            return false;
        }

        $logprob = $this->metric($segment, 'avg_logprob');
        if ($logprob !== null && $logprob <= self::MIN_AVG_LOGPROB) {
            return false;
        }

        $noSpeech = $this->metric($segment, 'no_speech_prob');

        return $noSpeech === null || $noSpeech < self::NO_SPEECH_PROB;
    }

    /** @param  array<string, mixed>  $segment */
    private function metric(array $segment, string $key): ?float
    {
        if (! isset($segment[$key]) || ! is_numeric($segment[$key])) {
            return null;
        }

        return (float) $segment[$key];
    }

    private function isNonSpeech(string $line): bool
    {
        $speech = $this->stripSpeaker($line);

        if ($speech === '' || $this->isPromptLeak($speech)) {
            return true;
        }

        $comparable = $this->comparable($speech);

        if ($comparable === '' || $comparable === 'نامفهوم') {
            return $comparable === '';
        }

        if (preg_match('/^(?:لا|نا|آه|آآ|اا|ها)(?: (?:لا|نا|آه|آآ|اا|ها)){2,}$/u', $comparable) === 1) {
            return true;
        }

        if (preg_match('/^(?:بوق|زنگ|موسیقی|آهنگ)(?: (?:بوق|زنگ|موسیقی|آهنگ))*$/u', $comparable) === 1) {
            return true;
        }

        if (preg_match('/^(?:صدای )?(?:بوق|زنگ)(?: (?:ممتد|اشغال|خوردن|می خورد))?$/u', $comparable) === 1) {
            return true;
        }

        if (preg_match('/^(?:در حال پخش |پخش )?(?:موسیقی|آهنگ)(?: (?:انتظار|پس زمینه|در حال پخش))?$/u', $comparable) === 1) {
            return true;
        }

        if (preg_match('/^(?:music|silence|ringing|hold music|background music)$/u', $comparable) === 1) {
            return true;
        }

        return mb_strlen($comparable) <= 80 && preg_match(
            '/(?:زیرنویس|ممنون که تماشا کردید|برای تماشای ویدیوهای بیشتر|thanks for watching|subscribe)/iu',
            $speech,
        ) === 1;
    }

    /** @param  list<string>  $lines */
    private function dropRepeatedHallucinations(array $lines): array
    {
        $collapsed = [];
        $total = count($lines);
        $index = 0;

        while ($index < $total) {
            $end = $index + 1;
            $key = $this->comparable($lines[$index]);

            while ($end < $total && $this->comparable($lines[$end]) === $key) {
                $end++;
            }

            $run = $end - $index;
            $length = mb_strlen($key);

            if ($run >= self::HALLUCINATED_REPEAT_MIN_COUNT && $length >= self::HALLUCINATED_REPEAT_MIN_CHARS) {
                $index = $end;

                continue;
            }

            $copies = ($run >= self::HALLUCINATED_REPEAT_MIN_COUNT && $length > 0) ? 2 : $run;
            for ($cursor = 0; $cursor < $copies; $cursor++) {
                $collapsed[] = $lines[$index];
            }

            $index = $end;
        }

        $counts = array_count_values(array_map($this->comparable(...), $collapsed));
        $kept = [];

        foreach ($collapsed as $line) {
            $key = $this->comparable($line);
            if (mb_strlen($key) >= self::HALLUCINATED_REPEAT_MIN_CHARS && ($counts[$key] ?? 0) >= self::HALLUCINATED_REPEAT_MIN_COUNT) {
                continue;
            }

            $kept[] = $line;
        }

        return $kept;
    }

    private function isPromptLeak(string $speech): bool
    {
        return str_contains($speech, 'فقط حرف‌های واقعی آدم‌ها را بنویس')
            || str_contains($speech, 'فقط حرف واقعی را بنویس')
            || str_contains($speech, 'به‌جایشان کلمه یا شعر نساز')
            || str_contains($speech, 'به‌جایشان کلمه نساز')
            || str_contains($speech, 'این نام شرکت مشتری نیست')
            || str_contains($speech, 'رونویسی لفظ‌به‌لفظ مکالمه تلفنی فارسی');
    }

    private function stripSpeaker(string $line): string
    {
        $stripped = preg_replace('/^(?:کارشناس|مشتری|نامشخص|گوینده\s+\S+)\s*[:：]\s*/u', '', trim($line));

        return trim(is_string($stripped) ? $stripped : trim($line));
    }

    private function comparable(string $line): string
    {
        $line = str_replace(["\u{200c}", "\u{200d}", 'ي', 'ك', 'ة'], [' ', ' ', 'ی', 'ک', 'ه'], $this->stripSpeaker($line));
        $line = mb_strtolower($line);
        $line = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $line) ?? $line;
        $line = preg_replace('/\s+/u', ' ', $line) ?? $line;

        return trim($line);
    }

    private function compact(string $value): string
    {
        return preg_replace('/\s+/u', '', $value) ?? $value;
    }
}
