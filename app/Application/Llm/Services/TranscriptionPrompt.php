<?php

namespace App\Application\Llm\Services;

use App\Domain\Llm\DTOs\PromptContextData;

class TranscriptionPrompt
{
    public static function instructions(): string
    {
        return <<<'PROMPT'
فقط رونویس مکالمه تلفنی فارسی. خروجی فقط خط گفتگو باشد. خلاصه، تحلیل و توضیح ننویس.
نقش را قبل از نوشتن مشخص کن و برچسب را تا پایان عوض نکن:
- نام سازمان و نام کارشناس در زمینه همیشه طرف کارشناس است. این نام‌ها را به مشتری نده.
- تماس ورودی: کسی که جواب می‌دهد و سازمان را معرفی می‌کند کارشناس است. کسی که دلیل تماس را می‌گوید مشتری است.
- تماس خروجی: کسی که زنگ زده و سازمان را معرفی می‌کند کارشناس است. طرف مقابل مشتری است.
- پیگیری پول: کسی که از طرف سازمان طلب را می‌خواهد کارشناس است.
- «من فلان هستم» فقط به همان گوینده تعلق دارد.
هر نوبت یک خط: «کارشناس:» یا «مشتری:». نوبت‌های پشت‌سرهم یک نفر را در همان خط ادغام کن. از «نامشخص» فقط وقتی استفاده کن که واقعاً معلوم نیست.
بوق، زنگ، موسیقی انتظار، آهنگ بین داخلی، سکوت و صدای دکمه را حذف کن و به‌جایشان کلمه نساز.
«آهان»، «خب» و «بله» تکراری را اگر جواب سؤال نیستند حذف کن. نام، عدد، قیمت، کد، اعتراض و قول را همان‌طور که گفته شده بنویس و حدس نزن. حرف نامفهوم انسان را [نامفهوم] بگذار.
چیزی را که در صدا گفته نشده از زمینه وارد متن نکن. تا آخرین حرف واقعی را بنویس.
PROMPT;
    }

    public static function speechApiPrompt(?PromptContextData $context = null): string
    {
        $parts = ['فقط حرف واقعی را بنویس. بوق، زنگ و موسیقی انتظار را حذف کن و به‌جایشان کلمه نساز. هر نوبت با «کارشناس:» یا «مشتری:» باشد.'];

        $organization = trim((string) $context?->organizationName);
        $agent = trim((string) $context?->employeeName);
        $direction = trim((string) $context?->callDirection);

        if ($organization !== '') {
            $parts[] = "سازمان کارشناس: {$organization}. این نام شرکت مشتری نیست.";
        }
        if ($agent !== '') {
            $parts[] = "نام کارشناس: {$agent}. این نام مشتری نیست.";
        }
        if ($direction !== '') {
            $parts[] = "جهت تماس: {$direction}.";
        }

        return implode(' ', $parts);
    }

    /**
     * Groq and OpenAI Whisper use this as a style and spelling hint.
     * An instruction is copied into the transcript, so this stays a short word list.
     */
    public static function whisperPrompt(?PromptContextData $context = null): string
    {
        $names = [];

        foreach ([$context?->employeeName, $context?->organizationName] as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $names[] = $value;
            }
        }

        $prompt = 'واژه‌ها: سلام، وقت بخیر، بله، متوجه شدم';
        if ($names !== []) {
            $prompt .= '، '.implode('، ', $names);
        }

        return mb_substr($prompt.'.', 0, 180);
    }

    public static function isWhisperModel(string $model): bool
    {
        $key = strtolower(trim($model));
        if (str_contains($key, '/')) {
            $key = (string) substr($key, (int) strrpos($key, '/') + 1);
        }

        return str_contains($key, 'whisper');
    }

    public static function needsSpeakerLabels(string $transcript): bool
    {
        $lines = preg_split('/\R/u', trim($transcript)) ?: [];
        $lines = array_values(array_filter(
            array_map(trim(...), $lines),
            fn (string $line): bool => $line !== '',
        ));

        if ($lines === []) {
            return false;
        }

        $labeled = 0;
        foreach ($lines as $line) {
            if (preg_match('/^(?:کارشناس|مشتری)\s*[:：]/u', $line) === 1) {
                $labeled++;
            }
        }

        return ($labeled / count($lines)) < 0.5;
    }

    public static function speakerTurnSystem(): string
    {
        return <<<'PROMPT'
این متن رونویسی ویسپر است و گوینده ندارد. فقط همان واژه‌ها را به نوبت گفتگو تقسیم کن.
فقط یک شیء JSON با کلید transcript برگردان.
هر نوبت یک خط و با «کارشناس:» یا «مشتری:» شروع شود.
نام کارشناس و نام سازمان در زمینه، طرف کارشناس است. این نام‌ها را به مشتری نده.
تماس ورودی: کسی که سازمان را معرفی می‌کند کارشناس است. تماس خروجی: کسی که زنگ زده و سازمان را معرفی می‌کند کارشناس است.
پیگیری پول: کسی که از طرف سازمان طلب را می‌خواهد کارشناس است. «من فلان هستم» فقط به همان گوینده تعلق دارد.
جمله تازه نساز، خلاصه نکن و واژه‌ای را حذف نکن. برچسب نامشخص نگذار.
PROMPT;
    }

    public static function speakerTurnUser(?PromptContextData $context, string $transcript): string
    {
        $lines = ['متن زیر را بدون تغییر واژه‌ها به خط‌های کارشناس و مشتری تقسیم کن.'];
        $organization = trim((string) $context?->organizationName);
        $agent = trim((string) $context?->employeeName);
        $direction = trim((string) $context?->callDirection);

        if ($organization !== '') {
            $lines[] = "سازمان کارشناس: {$organization}";
        }
        if ($agent !== '') {
            $lines[] = "نام کارشناس: {$agent}";
        }
        if ($direction !== '') {
            $lines[] = 'جهت: '.self::directionHint($direction);
        }

        $lines[] = "متن:\n".trim($transcript);

        return implode("\n", $lines);
    }

    public static function userTurn(?PromptContextData $context, string $transcriptSoFar = ''): string
    {
        $lines = ['صدا را رونویسی کن. بوق و موسیقی انتظار را حذف کن.'];

        $organization = trim((string) $context?->organizationName);
        $agent = trim((string) $context?->employeeName);
        $direction = trim((string) $context?->callDirection);

        if ($organization !== '') {
            $lines[] = "سازمان کارشناس: {$organization}. این نام شرکت مشتری نیست.";
        }
        if ($agent !== '') {
            $lines[] = "نام کارشناس: {$agent}. این نام مشتری نیست.";
        }
        if ($direction !== '') {
            $lines[] = 'جهت: '.self::directionHint($direction);
        }

        $soFar = trim($transcriptSoFar);
        if ($soFar !== '') {
            $tail = mb_substr($soFar, -240);
            $lines[] = "ادامه بده و این بخش را تکرار نکن:\n{$tail}";
        }

        return implode("\n", $lines);
    }

    private static function directionHint(string $direction): string
    {
        $normalized = mb_strtolower(trim($direction));

        return match ($normalized) {
            'inbound', 'in', 'incoming' => 'ورودی. پاسخ‌دهنده کارشناس است.',
            'outbound', 'out', 'outgoing' => 'خروجی. تماس‌گیرنده کارشناس است.',
            default => $direction,
        };
    }

    public static function append(string $existing, string $next): string
    {
        $existing = trim($existing);
        $next = self::unwrap($next);

        if ($next === '') {
            return $existing;
        }

        if ($existing === '' || str_starts_with($next, $existing)) {
            return $next;
        }

        $max = min(mb_strlen($existing), mb_strlen($next), 400);

        for ($length = $max; $length >= 20; $length--) {
            $suffix = mb_substr($existing, -$length);
            if (str_starts_with($next, $suffix)) {
                $remainder = trim(mb_substr($next, $length));

                return $remainder === '' ? $existing : $existing."\n".$remainder;
            }
        }

        return $existing."\n".$next;
    }

    public static function unwrap(string $content): string
    {
        $content = trim($content);

        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```[a-zA-Z]*\s*|\s*```$/', '', $content) ?? $content;
            $content = trim($content);
        }

        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            $transcript = $decoded['transcript'] ?? $decoded['text'] ?? null;
            if (is_string($transcript) && trim($transcript) !== '') {
                return trim($transcript);
            }
        }

        return $content;
    }

    public static function isTruncated(?string $finishReason): bool
    {
        $reason = strtolower(trim((string) $finishReason));

        return in_array($reason, ['length', 'max_tokens', 'max_tokens_exceeded'], true);
    }

    public static function maxContinuations(): int
    {
        return max(1, (int) config('llm.transcription.max_continuations', 4));
    }
}
