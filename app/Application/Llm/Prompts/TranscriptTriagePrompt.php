<?php

namespace App\Application\Llm\Prompts;

use App\Domain\Llm\DTOs\PromptContextData;

final class TranscriptTriagePrompt
{
    public static function system(): string
    {
        return <<<'PROMPT'
تو فقط تماس را دسته‌بندی می‌کنی و فقط یک شیء JSON برمی‌گردانی.
- kind فقط یکی از real ، personal ، none
- confidence عدد اعشاری صفر تا یک
- summary برای personal و none یک یا دو جمله فارسی است. برای real رشته خالی بگذار
- personal_reason برای personal یک جمله کوتاه فارسی است. در غیر این صورت رشته خالی

real یعنی فروش، پشتیبانی، پیگیری پرداخت، یا هماهنگی کاری خود سازمان. اگر کارشناس از طرف سازمان پول می‌خواهد، real است.
personal یعنی کارشناس برای کار خودش تماس گرفته است: سفارش غذا، خرید شخصی، خانواده یا دوست.
none یعنی مکالمه شکل نگرفته: سکوت، بوق، موسیقی انتظار، یا سلام و قطع بدون موضوع.
نام کارشناس و نام سازمان زمینه، هویت مشتری نیست.

اگر تردید داری kind را real بگذار و confidence را پایین بگذار.
PROMPT;
    }

    public static function user(PromptContextData $context, string $transcript): string
    {
        $lines = ['متن مکالمه را دسته‌بندی کن.'];
        $organization = trim((string) $context->organizationName);
        $business = trim((string) $context->organizationBusinessContext);
        $agent = trim((string) $context->employeeName);
        $direction = trim((string) $context->callDirection);

        if ($organization !== '') {
            $lines[] = "سازمان: {$organization}";
        }
        if ($business !== '') {
            $lines[] = "زمینه فعالیت سازمان: {$business}";
        }
        if ($agent !== '') {
            $lines[] = "کارشناس: {$agent}";
        }
        if ($direction !== '') {
            $lines[] = "جهت تماس: {$direction}";
        }

        $lines[] = "متن مکالمه:\n{$transcript}";

        return implode("\n", $lines);
    }
}
