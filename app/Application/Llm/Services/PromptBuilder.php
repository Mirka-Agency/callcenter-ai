<?php

namespace App\Application\Llm\Services;

use App\Domain\Llm\DTOs\AudioAnalysisRequestData;

class PromptBuilder
{
    public static function organizationDomainPolicy(): string
    {
        return <<<'PROMPT'
قوانین تفسیر دامنه سازمان (الزامی در صورت وجود زمینه فعالیت):
- When organization business context is provided in the user/context prompt, treat it as ground truth for domain, specialty, services, and vocabulary.
- Use that context to interpret ambiguous, incomplete, or phonetically similar speech (e.g. do not invent unrelated specialties or services).
- Prefer domain-consistent terms, product/service names, and medical or industry vocabulary from the org context over generic or out-of-domain guesses.
- Do not substitute services or topics that contradict the organization context (for example, do not report cosmetic/skin-laser services for an endocrine clinic).
- When building summary, intent, important_keywords, strengths, weaknesses, and customer_insights, stay aligned with the stated organization domain.
- If business context is absent, analyze from audio and other metadata alone as usual.

قوانین دامنه فعالیت سازمان:
- اگر «زمینه فعالیت سازمان» در زمینه تماس آمده، آن را مرجع حوزه تخصص، خدمات و واژگان بدانید
- گفتار مبهم یا شبیه از نظر آوایی را با توجه به همین زمینه تفسیر کنید؛ تخصص یا خدمات نامرتبط اختراع نکنید
- واژه‌ها و خدمات هم‌راستا با دامنه سازمان را به حدس‌های عمومی یا خارج از حوزه ترجیح دهید
- خدمات یا موضوعاتی که با زمینه سازمان تناقض دارند ثبت نکنید
- در خلاصه، intent، important_keywords و بینش‌های مشتری با دامنه اعلام‌شده سازمان هم‌خوان باشید
- اگر زمینه فعالیت خالی است، فقط بر اساس صوت و سایر فراداده‌ها تحلیل کنید
PROMPT;
    }

    public static function weaknessEvaluationPolicy(): string
    {
        return <<<'PROMPT'
قوانین ارزیابی نقاط ضعف:
- کلمات فنی انگلیسی، نام محصولات، اصطلاحات صنعتی و اصطلاحات تخصصی (مانند file، CRM، API، login، upload) نقاط ضعف محسوب نمی‌شوند.
- استفاده ترکیبی از فارسی و انگلیسی در گفتار حرفه‌ای جریمه نشود.
- فقط موارد زیر را به‌عنوان نقطه ضعف ثبت کن:
  - گفتار نامفهوم یا مبهم
  - ارتباط نادرست یا ضعیف با مشتری
  - رعایت نکردن مراحل صحیح فرآیند تماس
  - رفتار نامناسب در برخورد با مشتری
  - خطاهای واقعی در ارائه اطلاعات یا خدمات
PROMPT;
    }

    public static function summaryPolicy(): string
    {
        return <<<'PROMPT'
Generate a detailed business summary in Persian.

The summary must explain:
- why the customer contacted us
- what was discussed
- what concerns were raised
- what decisions were made
- what follow-up actions are required

Prefer a comprehensive summary over a very short summary.

قوانین تولید خلاصه (فیلد summary):
- یک خلاصه کسب‌وکاری مفصل به فارسی بنویسید، نه فقط چند جمله کوتاه
- معمولاً ۱ تا ۳ پاراگراف (حدود ۱۰۰ تا ۳۰۰ کلمه) باشد؛ تماس‌های طولانی‌تر خلاصه مفصل‌تری بگیرند
- به‌صورت روایت طبیعی فارسی بنویسید، نه فقط بولت‌پوینت
- این موارد را پوشش دهید:
  - دلیل اصلی تماس
  - درخواست یا پرسش مشتری
  - سوالات مهم مشتری
  - پاسخ‌های کلیدی کارشناس
  - دغدغه‌ها یا اعتراضات مهم
  - نتیجه کلی مکالمه
  - اقدامات بعدی توافق‌شده
  - نیازهای پیگیری
- رونوشت را تکرار نکنید؛ اطلاعات را فشرده و برای تصمیم‌گیری مدیران، سرپرستان، فروش و CRM مفید کنید
- از بازگوی جمله‌به‌جمله، جزئیات بی‌اهمیت و محتوای پرکننده خودداری کنید
- خواننده باید بدون خواندن رونوشت کامل، مکالمه را درک کند
PROMPT;
    }

    public static function customerIdentityPolicy(): string
    {
        return <<<'PROMPT'
You are provided with the current CRM user name and current CRM company name.
These belong to the sales agent or organization using the CRM.
Do NOT identify these values as customer information unless the conversation explicitly proves otherwise.
Extract only the customer's identity and company information.

قوانین استخراج هویت مشتری:
- نام کامل مشتری، نام شرکت، سازمان، برند یا کسب‌وکار را از گفتار مشتری استخراج کنید
- فیلد customer_identity (شیء) را برگردانید:
  - person_name (رشته فارسی — نام مشتری؛ خالی اگر شناسایی نشد)
  - company_name (رشته فارسی — نام شرکت/سازمان/برند مشتری؛ خالی اگر شناسایی نشد)
  - email (رشته — فقط اگر صریحاً در مکالمه ذکر شد؛ در غیر این صورت خالی)
  - job_title (رشته فارسی — سمت شغلی مشتری؛ خالی اگر ذکر نشد)
  - phone_number (رشته — فقط اگر مشتری شماره خود را گفت؛ خالی در غیر این صورت)
  - confidence (عدد اعشاری ۰ تا ۱ — میزان اطمینان استخراج)
  - evidence (رشته فارسی — نقل‌قول یا جمله‌ای از مکالمه که استخراج را تأیید می‌کند)
- اگر اطلاعاتی موجود نیست یا اطمینان پایین است، فیلد را خالی بگذارید — هرگز حدس نزنید
- اگر تلفظ نامشخص، چند نام ذکر شده یا ارجاع مبهم به شرکت وجود دارد، confidence را پایین بگذارید
- نام کارشناس فروش و شرکت CRM را هرگز به‌عنوان هویت مشتری ثبت نکنید مگر اینکه مکالمه صریحاً خلاف آن را ثابت کند

قوانین ایزولاسیون چندمستاجری (الزامی):
- هویت مشتری استخراج‌شده فقط برای سازمان/مستاجر فعلی این تماس معتبر است
- هرگز فرض نکنید یک شماره تلفن در سازمان‌های دیگر همان مشتری است
- کلید هویت مشتری = سازمان فعلی + شماره تلفن — نه شماره تلفن به‌تنهایی
- داده‌های استخراج‌شده (نام، شرکت، ایمیل، سمت) فقط در محیط همین سازمان ذخیره و استفاده می‌شوند
PROMPT;
    }

    public static function leadAnalysisPolicy(): string
    {
        return <<<'PROMPT'
علاوه بر تحلیل تماس، باید:
1. کیفیت لید مشتری را بر اساس سیگنال‌های تمایل به خرید ارزیابی کنید
2. دغدغه‌ها و اعتراضات مشتری را به‌صورت صریح استخراج کنید
3. برای هر دو فیلد lead_quality و concerns خروجی JSON ساختاریافته تولید کنید
4. سیگنال‌های ضمنی تمایل به خرید را نادیده نگیرید
5. با نگاه فروش و تبدیل مشتری تحلیل کنید

فیلدهای اجباری جدید:
- lead_quality (شیء):
  - score (عدد ۰ تا ۱۰۰)
  - level (رشته: low, medium, high)
  - reason (رشته فارسی — توضیح کیفیت لید)
  - buying_intent_signals (آرایه رشته‌های فارسی — نشانه‌های تمایل به خرید)
- concerns (آرایه‌ای از اشیاء):
  - type (رشته: price, trust, timing, technical, other)
  - text (رشته فارسی — شرح دغدغه یا اعتراض)
  - severity (رشته: low, medium, high)

در ارزیابی lead_quality این موارد را در نظر بگیرید: احتمال خرید، سیگنال‌های فوریت، نشانه‌های بودجه، جدیت پرسش مشتری، احتمال تبدیل.
PROMPT;
    }

    public static function persianLanguagePolicy(): string
    {
        return <<<'PROMPT'
تمام خروجی را فقط به زبان فارسی تولید کن.
هیچ کلمه انگلیسی استفاده نکن.
حتی نام بخش‌ها و لیبل‌ها هم فارسی باشند.
تمام جملات، توضیحات، نقاط قوت، نقاط ضعف، اقدامات بعدی و خلاصه باید کاملاً فارسی باشند.
مقادیر sentiment فقط یکی از این‌ها باشد: positive, neutral, negative, mixed
مقادیر urgency_level فقط یکی از این‌ها باشد: low, medium, high, critical
مقادیر risk_level فقط یکی از این‌ها باشد: low, medium, high
مقادیر lead_quality.level فقط یکی از این‌ها باشد: low, medium, high
مقادیر concerns.type فقط یکی از این‌ها باشد: price, trust, timing, technical, other
مقادیر concerns.severity فقط یکی از این‌ها باشد: low, medium, high
PROMPT;
    }

    public static function persianStrictRetryPolicy(): string
    {
        return <<<'PROMPT'
هشدار: خروجی قبلی شامل متن انگلیسی بود.
دوباره تحلیل کن و این بار فقط فارسی بنویس.
هیچ کلمه، عبارت یا جمله انگلیسی در هیچ فیلدی نباشد.
PROMPT;
    }

    public function systemPrompt(?string $version = null): string
    {
        $base = null;

        if ($version) {
            $prompt = \App\Models\LlmPromptVersion::query()
                ->where('version', $version)
                ->where('is_active', true)
                ->first();

            if ($prompt) {
                $base = $prompt->system_prompt;
            }
        }

        if ($base === null) {
            $default = \App\Models\LlmPromptVersion::query()->where('is_active', true)->first();

            if ($default) {
                $base = $default->system_prompt;
            }
        }

        if ($base === null) {
            $base = <<<'PROMPT'
شما یک تحلیل‌گر حرفه‌ای کیفیت تماس در مرکز تماس هستید. به مکالمه صوتی پیوست‌شده گوش دهید و یک شی JSON با کلیدهای دقیق زیر برگردانید:

- score (عدد صحیح ۰ تا ۱۰۰، امتیاز کلی عملکرد کارشناس)
- summary (رشته — خلاصه کسب‌وکاری مفصل فارسی؛ معمولاً ۱ تا ۳ پاراگراف و حدود ۱۰۰ تا ۳۰۰ کلمه شامل دلیل تماس، موضوعات، دغدغه‌ها، پاسخ‌های کلیدی، نتیجه و اقدامات بعدی)
- sentiment (رشته: positive, neutral, negative, mixed)
- overall_evaluation (رشته، ارزیابی کوتاه فارسی از عملکرد)
- strengths (آرایه‌ای از رشته‌های فارسی — نقاط قوت)
- weaknesses (آرایه‌ای از رشته‌های فارسی — فقط نقاط ضعف رفتاری و ارتباطی واقعی؛ هرگز کلمات فنی انگلیسی یا اصطلاحات تخصصی را به‌عنوان نقطه ضعف ذکر نکن)
- next_actions (آرایه‌ای از رشته‌های فارسی — اقدامات پیشنهادی برای بهبود)
- input_tokens (عدد صحیح)
- output_tokens (عدد صحیح)
- total_tokens (عدد صحیح)
- cost (عدد)
- model (رشته)
- performance_dimensions (شیء با امتیاز ۰ تا ۱۰۰ برای هر کلید):
  - communication_skills
  - product_knowledge
  - objection_handling
  - closing_ability
  - professionalism
- customer_insights (شیء):
  - sentiment (رشته: positive, neutral, negative, mixed)
  - intent (رشته فارسی — خواسته مشتری)
  - purchase_probability (عدد ۰ تا ۱۰۰)
  - urgency_level (رشته: low, medium, high, critical)
  - risk_level (رشته: low, medium, high)
- operational_insights (شیء):
  - missed_opportunities (آرایه رشته‌های فارسی)
  - escalation_risks (آرایه رشته‌های فارسی)
  - compliance_issues (آرایه رشته‌های فارسی)
  - important_keywords (آرایه رشته‌های فارسی)
  - follow_up_suggestions (آرایه رشته‌های فارسی)
- lead_quality (شیء):
  - score (عدد ۰ تا ۱۰۰)
  - level (رشته: low, medium, high)
  - reason (رشته فارسی)
  - buying_intent_signals (آرایه رشته‌های فارسی)
- concerns (آرایه اشیاء):
  - type (رشته: price, trust, timing, technical, other)
  - text (رشته فارسی)
  - severity (رشته: low, medium, high)
- customer_identity (شیء):
  - person_name (رشته فارسی — نام مشتری)
  - company_name (رشته فارسی — نام شرکت/سازمان/برند مشتری)
  - confidence (عدد اعشاری ۰ تا ۱)
  - evidence (رشته فارسی — جمله استخراج‌شده از مکالمه)

منصفانه، سازنده و دقیق باشید. روی مهارت ارتباطی، حل مسئله، همدلی، انطباق و فرصت‌های فروش تمرکز کنید.
PROMPT;
        }

        return trim($base)."\n\n".self::persianLanguagePolicy()."\n\n".self::summaryPolicy()."\n\n".self::organizationDomainPolicy()."\n\n".self::weaknessEvaluationPolicy()."\n\n".self::leadAnalysisPolicy()."\n\n".self::customerIdentityPolicy();
    }

    public function contextPrompt(AudioAnalysisRequestData $request): string
    {
        $context = $request->context;
        $meta = [];

        if ($context?->organizationName) {
            $meta[] = "سازمان: {$context->organizationName}";
        }
        if ($context?->organizationBusinessContext) {
            $businessContext = trim($context->organizationBusinessContext);
            if ($businessContext !== '') {
                $meta[] = "زمینه فعالیت سازمان: {$businessContext}";
            }
        }
        if ($context?->employeeName) {
            $meta[] = "کارشناس: {$context->employeeName}";
        }
        if ($context?->department) {
            $meta[] = "دپارتمان: {$context->department}";
        }
        if ($context?->position) {
            $meta[] = "سمت: {$context->position}";
        }
        if ($context?->title) {
            $meta[] = "عنوان: {$context->title}";
        }
        if ($context?->customerName) {
            $meta[] = "نام مشتری: {$context->customerName}";
        }
        if ($context?->customerNumber) {
            $meta[] = "شماره مشتری: {$context->customerNumber}";
        }
        if ($context?->category) {
            $meta[] = "دسته‌بندی: {$context->category}";
        }
        if ($context?->callDirection) {
            $meta[] = "جهت تماس: {$context->callDirection}";
        }
        if ($context?->callDurationSeconds) {
            $meta[] = "مدت تماس: {$context->callDurationSeconds} ثانیه";
        }
        if ($context?->notes) {
            $meta[] = "یادداشت‌ها: {$context->notes}";
        }

        $crmContext = array_filter([
            'current_user_name' => $context?->employeeName,
            'current_company_name' => $context?->organizationName,
        ], fn (mixed $value) => is_string($value) && trim($value) !== '');

        if ($crmContext !== []) {
            $meta[] = 'زمینه CRM: '.json_encode($crmContext, JSON_UNESCAPED_UNICODE);
        }

        $header = $meta !== [] ? "زمینه:\n".implode("\n", $meta)."\n\n" : '';

        return $header.'مکالمه صوتی پیوست‌شده را تحلیل کن. خلاصه (summary) باید مفصل و کسب‌وکاری باشد. JSON خواسته‌شده را فقط به فارسی برگردان.';
    }

    /** @return list<array{role: string, content: mixed}> */
    public function buildAudioMessages(
        AudioAnalysisRequestData $request,
        string $audioFormat = 'mp3',
        bool $strictPersian = false,
        ?string $playbackUrl = null,
        ?string $audioBase64 = null,
        ?string $mimeType = null,
    ): array {
        $systemPrompt = $this->systemPrompt($request->promptVersion);

        if ($strictPersian) {
            $systemPrompt .= "\n\n".self::persianStrictRetryPolicy();
        }

        $userContent = [
            ['type' => 'text', 'text' => $this->contextPrompt($request)],
        ];

        if ($audioBase64) {
            $inputAudio = [
                'data' => $audioBase64,
                'format' => $audioFormat,
            ];

            if ($mimeType) {
                $inputAudio['mime_type'] = $mimeType;
            }

            $userContent[] = [
                'type' => 'input_audio',
                'input_audio' => $inputAudio,
            ];
        } elseif ($playbackUrl) {
            $userContent[] = [
                'type' => 'input_audio',
                'input_audio' => [
                    'url' => $playbackUrl,
                    'format' => $audioFormat,
                ],
            ];
        }

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userContent],
        ];
    }
}
