<?php

namespace App\Application\Llm\Services;

use App\Domain\Llm\DTOs\PromptContextData;

class TranscriptionPrompt
{
    public static function instructions(): string
    {
        return <<<'PROMPT'
شما فقط رونویس مکالمه تلفنی فارسی هستید.
خروجی فقط متن گفتگو باشد. خلاصه، تحلیل، عنوان، مارک‌داون و توضیح ننویسید.
فقط حرفی را بنویسید که یک انسان واقعاً گفته است. هیچ حرف واقعی را حذف، ادغام، ترجمه یا اصلاح نکنید.
این صداها گفتار نیستند و نباید به متن تبدیل شوند: بوق، زنگ خوردن، بوق اشغال، موسیقی انتظار، آهنگ بین وصل شدن به داخلی دیگر، سکوت، صدای دکمه‌ها و بوق دستگاه.
برای این صداها کلمه، شعر، توضیح یا دیالوگ نسازید. آن بخش را کاملاً حذف کنید و از ادامه حرف واقعی بنویسید.
هر نوبت گفتار در یک خط جدا و با یکی از این برچسب‌ها:
کارشناس: ...
مشتری: ...
نامشخص: ...
کلمات نامفهوم انسان را با [نامفهوم] علامت بزنید و به‌جای آن حدس نزنید. بوق و آهنگ را [نامفهوم] ننویسید؛ آن‌ها را حذف کنید.
اعداد، نام‌ها، کدها و اصطلاحات را همان‌طور که شنیده می‌شوند بنویسید.
از اطلاعات زمینه فقط برای تشخیص کارشناس از مشتری استفاده کنید. اگر چیزی در صدا گفته نشده، آن را در متن نیاورید.
تا آخرین حرف واقعی فایل را بنویسید.
PROMPT;
    }

    public static function speechApiPrompt(): string
    {
        return 'فقط حرف‌های واقعی آدم‌ها را بنویس. بوق، زنگ، موسیقی انتظار، آهنگ، سکوت و صدای دکمه‌ها را ننویس و به‌جایشان کلمه یا شعر نساز. اگر تماس به داخلی دیگری وصل شد، آهنگ بین دو بخش را حذف کن و فقط حرف قبل و بعد را بنویس.';
    }

    public static function userTurn(?PromptContextData $context, string $transcriptSoFar = ''): string
    {
        $lines = ['فایل صوتی مکالمه پیوست شده است. فقط حرف‌های واقعی را بنویسید و بوق، زنگ و موسیقی انتظار را حذف کنید.'];

        $organization = trim((string) $context?->organizationName);
        $agent = trim((string) $context?->employeeName);
        $direction = trim((string) $context?->callDirection);

        if ($organization !== '') {
            $lines[] = "نام سازمان کارشناس: {$organization}";
        }
        if ($agent !== '') {
            $lines[] = "نام کارشناس: {$agent}";
        }
        if ($direction !== '') {
            $lines[] = "جهت تماس: {$direction}";
        }

        $soFar = trim($transcriptSoFar);
        if ($soFar !== '') {
            $tail = mb_substr($soFar, -800);
            $lines[] = "رونویسی تا اینجا انجام شده و به‌خاطر طول فایل قطع شده است. از ادامه همان‌جا بنویسید و بخش زیر را تکرار نکنید:\n{$tail}";
        }

        return implode("\n", $lines);
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
