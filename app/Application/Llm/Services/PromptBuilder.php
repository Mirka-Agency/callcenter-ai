<?php

namespace App\Application\Llm\Services;

use App\Domain\Llm\DTOs\AudioAnalysisRequestData;
use App\Domain\Llm\DTOs\PromptContextData;
use App\Domain\Voip\Enums\CallDirection;
use App\Models\LlmPromptVersion;

class PromptBuilder
{
    public static function organizationDomainPolicy(): string
    {
        return <<<'PROMPT'
قوانین تفسیر دامنه سازمان (الزامی در صورت وجود زمینه فعالیت):
- اگر «زمینه فعالیت سازمان» در زمینه تماس آمده، آن را حقیقت پایه برای حوزه تخصص، خدمات و واژگان بدانید
- گفتار مبهم، ناقص یا شبیه از نظر آوایی را با همین زمینه تفسیر کنید؛ تخصص یا خدمات نامرتبط اختراع نکنید
- واژه‌ها، نام خدمات و اصطلاحات هم‌راستا با دامنه سازمان را به حدس‌های عمومی یا خارج از حوزه ترجیح دهید
- خدمات یا موضوعاتی که با زمینه سازمان تناقض دارند ثبت نکنید؛ برای مثال برای کلینیک غدد، خدمات زیبایی یا لیزر پوست گزارش نکنید
- در خلاصه، خواسته مشتری، کلیدواژه‌های مهم، نقاط قوت، نقاط ضعف و بینش مشتری با دامنه اعلام‌شده سازمان هم‌خوان باشید
- اگر زمینه فعالیت خالی است، فقط بر اساس صوت و سایر فراداده‌ها تحلیل کنید
PROMPT;
    }

    public static function weaknessEvaluationPolicy(): string
    {
        return <<<'PROMPT'
قوانین ارزیابی نقاط ضعف:
- کلمات فنی لاتین، نام محصولات، اصطلاحات صنعتی و اصطلاحات تخصصی نقطه ضعف محسوب نمی‌شوند
- گفتار حرفه‌ای که واژه لاتین هم دارد جریمه نشود
- فقط موارد زیر را به‌عنوان نقطه ضعف ثبت کنید:
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
قوانین تولید خلاصه:
- یک خلاصه کسب‌وکاری مفصل، فقط به فارسی بنویسید؛ نه انگلیسی، نه چند جمله کوتاه
- معمولاً یک تا سه پاراگراف (حدود صد تا سیصد کلمه) باشد؛ تماس طولانی‌تر خلاصه مفصل‌تری بگیرد
- به‌صورت روایت طبیعی فارسی بنویسید، نه فقط فهرست گلوله‌ای
- این موارد را پوشش دهید:
  - دلیل اصلی تماس
  - درخواست یا پرسش مشتری
  - سوالات مهم مشتری
  - پاسخ‌های کلیدی کارشناس
  - دغدغه‌ها یا اعتراضات مهم
  - نتیجه کلی مکالمه
  - اقدامات بعدی توافق‌شده
  - نیازهای پیگیری
- متن مکالمه را تکرار نکنید؛ اطلاعات را فشرده و برای تصمیم‌گیری مدیران، سرپرستان، فروش و سامانه مشتریان مفید کنید
- از بازگوی جمله‌به‌جمله، جزئیات بی‌اهمیت و محتوای پرکننده خودداری کنید
- خواننده باید بدون خواندن متن کامل مکالمه، جریان تماس را درک کند
PROMPT;
    }

    public static function customerIdentityPolicy(): string
    {
        return <<<'PROMPT'
قوانین استخراج هویت مشتری:
- نام کارشناس فعلی و نام سازمان فعلی متعلق به فروشنده یا سازمان استفاده‌کننده از سامانه است
- این مقدارها را هویت مشتری ندانید مگر اینکه مکالمه صریحاً خلاف آن را ثابت کند
- فقط هویت و اطلاعات شرکت خودِ مشتری را استخراج کنید
- نام کامل مشتری، نام شرکت، سازمان، برند یا کسب‌وکار را از گفتار مشتری بگیرید
- فیلد customer_identity را به‌صورت شیء برگردانید:
  - person_name (رشته فارسی — نام مشتری؛ خالی اگر شناسایی نشد)
  - company_name (رشته فارسی — نام شرکت یا برند مشتری؛ خالی اگر شناسایی نشد)
  - email (رشته — فقط اگر صریحاً در مکالمه ذکر شد؛ وگرنه خالی)
  - job_title (رشته فارسی — سمت شغلی مشتری؛ خالی اگر ذکر نشد)
  - phone_number (رشته — فقط اگر مشتری شماره خود را گفت؛ وگرنه خالی)
  - confidence (عدد اعشاری صفر تا یک — میزان اطمینان استخراج)
  - evidence (رشته فارسی — نقل‌قول یا جمله‌ای از مکالمه که استخراج را تأیید می‌کند)
- اگر اطلاعاتی موجود نیست یا اطمینان پایین است، فیلد را خالی بگذارید؛ هرگز حدس نزنید
- اگر تلفظ نامشخص، چند نام ذکر شده یا ارجاع مبهم به شرکت وجود دارد، اطمینان را پایین بگذارید
- نام کارشناس فروش و شرکت سامانه را هرگز به‌عنوان هویت مشتری ثبت نکنید مگر اینکه مکالمه صریحاً خلاف آن را ثابت کند
- در تماس خروجی، کارشناس معمولاً در معرفی نام سازمان فعلی سامانه را می‌گوید؛ آن را نام شرکت مشتری ندانید
- company_name فقط وقتی پر شود که مشتری صریحاً نام شرکت خودش را گفته باشد، نه شرکت تماس‌گیرنده
- نام شرکت را با املای درست فارسی بنویسید؛ ي و ك عربی را به ی و ک فارسی تبدیل کنید
- فاصله‌های اضافه و پیشوند تکراری مثل «شرکت شرکت» را حذف کنید
- اگر همان شرکت قبلاً با املای درست‌تر مشخص شده، همان املای درست را برگردانید

قوانین ایزولاسیون چندمستاجری (الزامی):
- هویت مشتری استخراج‌شده فقط برای سازمان فعلی این تماس معتبر است
- هرگز فرض نکنید یک شماره تلفن در سازمان‌های دیگر همان مشتری است
- کلید هویت مشتری برابر است با سازمان فعلی به‌علاوه شماره تلفن؛ نه شماره تلفن به‌تنهایی
- داده‌های استخراج‌شده (نام، شرکت، ایمیل، سمت) فقط در محیط همین سازمان ذخیره و استفاده می‌شوند
PROMPT;
    }

    public static function leadAnalysisPolicy(): string
    {
        return <<<'PROMPT'
علاوه بر تحلیل تماس، باید:
1. کیفیت لید مشتری را بر اساس نشانه‌های تمایل به خرید ارزیابی کنید
2. دغدغه‌ها و اعتراضات مشتری را به‌صورت صریح استخراج کنید
3. برای هر دو فیلد lead_quality و concerns خروجی ساخت‌یافته تولید کنید
4. نشانه‌های ضمنی تمایل به خرید را نادیده نگیرید
5. با نگاه فروش و تبدیل مشتری تحلیل کنید

فیلدهای اجباری:
- lead_quality (شیء):
  - score (عدد صفر تا صد)
  - level (فقط یکی از: low ، medium ، high)
  - reason (رشته فارسی — توضیح کیفیت لید)
  - buying_intent_signals (آرایه رشته‌های فارسی — نشانه‌های تمایل به خرید)
- concerns (آرایه‌ای از اشیاء):
  - type (فقط یکی از: price ، trust ، timing ، technical ، other)
  - text (رشته فارسی — شرح دغدغه یا اعتراض)
  - severity (فقط یکی از: low ، medium ، high)

در ارزیابی کیفیت لید این موارد را در نظر بگیرید: احتمال خرید، نشانه‌های فوریت، نشانه‌های بودجه، جدیت پرسش مشتری، احتمال تبدیل.
PROMPT;
    }

    public static function sentimentPolicy(): string
    {
        return <<<'PROMPT'
قوانین دسته‌بندی احساس مشتری (الزامی):
- فیلدهای sentiment و customer_insights.sentiment باید یکسان باشند
- این فیلدها فقط احساس مشتری نسبت به برند، محصول و خدمات همین سازمان را نشان می‌دهند؛ نه لحن کلی تماس و نه کیفیت کار کارشناس
- negative را فقط و فقط وقتی بگذارید که مشتری از برند یا محصول یا خدمات این سازمان ناراضی است، یا نسبت به محصول یا برند اعتراض یا شکایت دارد
  - مثال‌های الزامی برای negative: محصول معیوب یا بی‌کیفیت، نارضایتی از خدمت یا سرویس دریافتی، خلف وعده برند، شکایت از عملکرد محصول، اعتراض به خدمات پس از فروش، تهدید به قطع همکاری به‌خاطر محصول یا برند
- اگر مشتری اعتراضی نسبت به محصول یا برند ما دارد، حتماً negative بگذارید
- هرگز فقط به‌خاطر موارد زیر negative نگذارید:
  - عجله، بی‌حوصلگی، لحن تند یا ناراحتی کلی مشتری
  - پرسش، استعلام قیمت، مقایسه، تردید خرید یا بی‌علاقگی به پیشنهاد فروش
  - نارضایتی از لحن یا عملکرد کارشناس در همین تماس، بدون اعتراض به برند، محصول یا خدمات سازمان
  - مشکل خط، قطع تماس یا کیفیت صوت
  - شکایت از رقیب یا موضوع نامرتبط با پیشنهاد این سازمان
- positive فقط وقتی مشتری رضایت صریح از برند، محصول یا خدمات نشان می‌دهد
- mixed فقط وقتی در همین تماس هم رضایت و هم نارضایتی نسبت به برند، محصول یا خدمات وجود دارد
- در سایر مکالمات معمولی فروش یا پشتیبانی، مقدار را neutral بگذارید
PROMPT;
    }

    public static function attentionPolicy(): string
    {
        return <<<'PROMPT'
تماس‌های نیازمند توجه (الزامی):
- اگر مشتری اعتراض یا شکایت مهمی دارد که مدیریت باید بداند، needs_attention.needed را درست بگذارید
- مثال‌ها: اعتراض به عملکرد کارشناس، اعتراض به محصول، اعتراض به سرویس یا خدمات، اعتراض کلی، تهدید به قطع همکاری، درخواست صحبت با مدیر، ریسک تشدید یا شکایت رسمی
- نگرانی معمولی مثل پرسش قیمت یا زمان تحویل به‌تنهایی نیازمند توجه نیست مگر با نارضایتی جدی یا درخواست پیگیری مدیریت همراه باشد
- اگر نیازمند توجه است، حداقل یک دسته از agent ، product ، service ، general ، other را در categories بنویسید و دلیل را به فارسی در reason توضیح دهید
- اگر نیازمند توجه نیست، needed را نادرست بگذارید، categories را آرایه خالی و reason را رشته خالی بگذارید
PROMPT;
    }

    public static function followUpPolicy(): string
    {
        return <<<'PROMPT'
قوانین پیشنهاد پیگیری تلفنی (الزامی):
- فیلد follow_up_suggestions فقط برای تماس تلفنی برگشتی با خودِ مشتری است؛ نه هر کار بعدی
- فقط وقتی حداقل یک رشته در این آرایه بگذارید که هر سه شرط همزمان برقرار باشد:
  1. مشتری در همین مکالمه درخواستی داشته که هنوز در همین تماس بسته نشده است
  2. کارشناس باید دوباره با همان مشتری تلفنی تماس بگیرد؛ مثل قول تماس فردا، اعلام نتیجه بعد از بررسی، یا پیگیری تلفنی تصمیم مشتری
  3. اقدام اصلی همان زنگ زدن مجدد به مشتری است، نه کار دیگری
- متن هر پیشنهاد باید صریحاً تماس تلفنی باشد؛ مثلاً «تماس پیگیری فردا برای اعلام نتیجه بررسی»
- این موارد را هرگز داخل follow_up_suggestions نگذارید؛ اگر لازم‌اند فقط در next_actions بیایند:
  - ارسال فایل، سند، کاتالوگ، پیش‌فاکتور، قرارداد، ویدیو یا لینک
  - پیام در واتساپ، تلگرام، اینستاگرام، پیامک، ایمیل یا هر شبکه اجتماعی
  - ثبت تیکت، پیگیری مشکل سیستمی، باگ، هماهنگی داخلی با واحد دیگر، یا یادآور داخل سامانه
  - تماس آنلاین، دمو، جلسه تصویری، یا تماس با واحد داخلی به‌جای خود مشتری
- اگر کارشناس فقط باید فایل یا پیام بفرستد و تماس تلفنی مجدد لازم نیست، follow_up_suggestions را آرایه خالی بگذارید
- اگر در همین تماس موضوع کامل حل شد و تماس برگشتی وعده داده نشد، آرایه را خالی بگذارید
- next_actions می‌تواند کارهای غیرتلفنی مثل ارسال فایل یا تیکت را داشته باشد؛ این فیلد را با follow_up_suggestions یکی نکنید
PROMPT;
    }

    public static function evaluableConversationPolicy(): string
    {
        return <<<'PROMPT'
ارزیابی‌پذیری مکالمه (الزامی):
- اگر تماس پاسخ داده شده ولی مکالمه معناداری رخ نداده (سکوت، فقط بوق یا موسیقی، قطع فوری بدون صحبت کارشناس و مشتری)، evaluable را نادرست بگذارید و score را صفر بگذارید
- امتیاز صفر یعنی «قابل ارزیابی نیست»، نه عملکرد ضعیف. مکالمه واقعی ضعیف را بین یک تا چهل امتیاز دهید؛ هرگز برای عملکرد ضعیف صفر ندهید
- وقتی مکالمه قابل ارزیابی نیست، در خلاصه صریحاً بنویسید مکالمه قابل ارزیابی نبود و کیفیت لید را لید واقعی در نظر نگیرید
PROMPT;
    }

    public static function persianLanguagePolicy(): string
    {
        return <<<'PROMPT'
قانون زبان (الزامی و مقدم بر هر دستور دیگر):
- مخاطب خروجی فارسی‌زبان است. تمام مقدارهای متنی را فقط به فارسی بنویسید
- جمله، پاراگراف، توضیح، خلاصه، ارزیابی، نقاط قوت، نقاط ضعف، اقدامات بعدی، خواسته مشتری، دغدغه، دلیل کیفیت لید و نقل‌قول شاهد باید کاملاً فارسی باشند
- هیچ جمله، عبارت یا توضیح انگلیسی ننویسید. حتی یک کلمه انگلیسی در مقدارهای متنی ممنوع است
- اگر واژه لاتین در صوت شنیده شد، معادل فارسی یا همان تلفظ فارسی‌شده را بنویسید؛ جمله را به انگلیسی ادامه ندهید
- نام کلیدهای خروجی را عوض نکنید؛ همان املای مشخص‌شده را نگه دارید. فقط مقدار متنی کلیدها فارسی باشد
- تنها استثنای لاتین در مقدارها، کدهای بسته زیر است و باید دقیقاً با همین املا بیایند:
  - احساس: positive یا neutral یا negative یا mixed
  - فوریت: low یا medium یا high یا critical
  - ریسک: low یا medium یا high
  - سطح لید: low یا medium یا high
  - نوع دغدغه: price یا trust یا timing یا technical یا other
  - شدت دغدغه: low یا medium یا high
  - دسته نیازمند توجه: agent یا product یا service یا general یا other
- به‌جز این کدها و نام کلیدها، هیچ حرف لاتین در متن نیاید
PROMPT;
    }

    public static function persianOutputSample(): string
    {
        return <<<'PROMPT'
نمونه خروجی درست (ساختار و زبان را از همین الگو پیروی کنید؛ محتوا را از مکالمه فعلی بسازید):
{
  "score": 78,
  "evaluable": true,
  "summary": "مشتری برای استعلام هزینه تمدید اشتراک تماس گرفت. کارشناس خدمات را توضیح داد، نگرانی قیمت را شنید و پیشنهاد تخفیف تمدید سالانه را مطرح کرد. در پایان قرار شد پیش‌فاکتور امروز ارسال شود و فردا برای تصمیم نهایی پیگیری شود.",
  "sentiment": "neutral",
  "overall_evaluation": "کارشناس مؤدب و مسلط بود، اما دعوت به تصمیم خرید را کمی دیر شروع کرد.",
  "strengths": ["لحن آرام و محترمانه", "توضیح شفاف خدمات"],
  "weaknesses": ["دعوت مستقیم به تصمیم خرید کمی دیر انجام شد"],
  "next_actions": ["ارسال پیش‌فاکتور در همین روز", "تماس پیگیری در روز بعد برای اعلام تصمیم مشتری"],
  "performance_dimensions": {
    "communication_skills": 86,
    "product_knowledge": 82,
    "objection_handling": 74,
    "closing_ability": 68,
    "professionalism": 88
  },
  "customer_insights": {
    "sentiment": "neutral",
    "intent": "استعلام هزینه و شرایط تمدید اشتراک",
    "purchase_probability": 62,
    "urgency_level": "medium",
    "risk_level": "low"
  },
  "operational_insights": {
    "missed_opportunities": ["پیشنهاد زمان مشخص برای نهایی کردن خرید"],
    "escalation_risks": [],
    "compliance_issues": [],
    "important_keywords": ["تمدید اشتراک", "تخفیف", "پیش‌فاکتور"],
    "follow_up_suggestions": ["تماس پیگیری در روز بعد برای اعلام تصمیم مشتری"]
  },
  "lead_quality": {
    "score": 70,
    "level": "medium",
    "reason": "مشتری هزینه و شرایط را جدی می‌پرسد و با ارسال پیش‌فاکتور موافقت کرده است.",
    "buying_intent_signals": ["پرسش درباره قیمت", "درخواست پیش‌فاکتور"]
  },
  "concerns": [
    {
      "type": "price",
      "text": "نگرانی از هزینه تمدید",
      "severity": "medium"
    }
  ],
  "needs_attention": {
    "needed": false,
    "categories": [],
    "reason": ""
  },
  "customer_identity": {
    "person_name": "",
    "company_name": "",
    "confidence": 0,
    "evidence": ""
  }
}
PROMPT;
    }

    public static function persianStrictRetryPolicy(): string
    {
        return <<<'PROMPT'
هشدار: خروجی قبلی شامل متن انگلیسی بود.
دوباره تحلیل کنید و این بار فقط فارسی بنویسید.
هیچ کلمه، عبارت یا جمله انگلیسی در هیچ فیلد متنی نباشد.
نام کلیدها را عوض نکنید. مقدارهای متنی را کامل به فارسی برگردانید.
PROMPT;
    }

    public function defaultSystemPrompt(): string
    {
        return <<<'PROMPT'
شما تحلیل‌گر حرفه‌ای کیفیت تماس در مرکز تماس هستید. به مکالمه صوتی پیوست‌شده گوش دهید و فقط یک خروجی ساخت‌یافته با کلیدهای دقیق زیر برگردانید. همه مقدارهای متنی باید فارسی باشند.

- score (عدد صحیح صفر تا صد، امتیاز کلی عملکرد کارشناس)
- evaluable (درست اگر مکالمه واقعی و قابل ارزیابی است؛ نادرست اگر تماس گرفته شد ولی صحبت معناداری نشد)
- summary (رشته فارسی — خلاصه کسب‌وکاری مفصل؛ معمولاً یک تا سه پاراگراف و حدود صد تا سیصد کلمه شامل دلیل تماس، موضوعات، دغدغه‌ها، پاسخ‌های کلیدی، نتیجه و اقدامات بعدی)
- sentiment (فقط یکی از: positive ، neutral ، negative ، mixed — احساس مشتری نسبت به برند، محصول و خدمات سازمان؛ negative فقط در صورت نارضایتی یا اعتراض به برند/محصول/خدمات)
- overall_evaluation (رشته فارسی، ارزیابی کوتاه از عملکرد)
- strengths (آرایه‌ای از رشته‌های فارسی — نقاط قوت)
- weaknesses (آرایه‌ای از رشته‌های فارسی — فقط نقاط ضعف رفتاری و ارتباطی واقعی؛ هرگز واژه فنی لاتین یا اصطلاح تخصصی را نقطه ضعف ندانید)
- next_actions (آرایه‌ای از رشته‌های فارسی — اقدامات بعدی شامل کارهای غیرتلفنی مثل ارسال فایل یا ثبت تیکت)
- input_tokens (عدد صحیح)
- output_tokens (عدد صحیح)
- total_tokens (عدد صحیح)
- cost (عدد)
- model (رشته)
- performance_dimensions (شیء با امتیاز صفر تا صد برای هر کلید):
  - communication_skills
  - product_knowledge
  - objection_handling
  - closing_ability
  - professionalism
- customer_insights (شیء):
  - sentiment (همان مقدار فیلد sentiment بالا؛ احساس نسبت به برند، محصول و خدمات سازمان)
  - intent (رشته فارسی — خواسته مشتری)
  - purchase_probability (عدد صفر تا صد)
  - urgency_level (فقط یکی از: low ، medium ، high ، critical)
  - risk_level (فقط یکی از: low ، medium ، high)
- operational_insights (شیء):
  - missed_opportunities (آرایه رشته‌های فارسی)
  - escalation_risks (آرایه رشته‌های فارسی)
  - compliance_issues (آرایه رشته‌های فارسی)
  - important_keywords (آرایه رشته‌های فارسی)
  - follow_up_suggestions (آرایه رشته‌های فارسی — فقط تماس تلفنی برگشتی با مشتری؛ اگر زنگ مجدد لازم نیست آرایه خالی)
- lead_quality (شیء):
  - score (عدد صفر تا صد)
  - level (فقط یکی از: low ، medium ، high)
  - reason (رشته فارسی)
  - buying_intent_signals (آرایه رشته‌های فارسی)
- concerns (آرایه اشیاء):
  - type (فقط یکی از: price ، trust ، timing ، technical ، other)
  - text (رشته فارسی)
  - severity (فقط یکی از: low ، medium ، high)
- needs_attention (شیء):
  - needed (درست اگر تماس داده مهمی برای پیگیری مدیریت دارد؛ مثل اعتراض مشتری به کارشناس، محصول، سرویس یا اعتراض کلی)
  - categories (آرایه؛ فقط از: agent ، product ، service ، general ، other)
  - reason (رشته فارسی — توضیح کوتاه دلیل توجه)
- customer_identity (شیء):
  - person_name (رشته فارسی — نام مشتری)
  - company_name (رشته فارسی — نام شرکت یا برند مشتری)
  - confidence (عدد اعشاری صفر تا یک)
  - evidence (رشته فارسی — جمله استخراج‌شده از مکالمه)

منصفانه، سازنده و دقیق باشید. روی مهارت ارتباطی، حل مسئله، همدلی، انطباق و فرصت‌های فروش تمرکز کنید.
PROMPT;
    }

    public function systemPrompt(?string $version = null): string
    {
        $base = $this->resolveBasePrompt($version);

        return implode("\n\n", [
            self::persianLanguagePolicy(),
            trim($base),
            self::persianOutputSample(),
            self::summaryPolicy(),
            self::organizationDomainPolicy(),
            self::weaknessEvaluationPolicy(),
            self::leadAnalysisPolicy(),
            self::sentimentPolicy(),
            self::attentionPolicy(),
            self::followUpPolicy(),
            self::customerIdentityPolicy(),
            self::evaluableConversationPolicy(),
        ]);
    }

    public function contextPrompt(AudioAnalysisRequestData $request): string
    {
        $context = $request->context;

        $sections = [
            $this->labeledBlock('سازمان', $this->contextValue($context?->organizationName)),
            $this->labeledBlock('کارشناس', $this->contextValue($context?->employeeName)),
            $this->labeledBlock('نقش کارشناس', $this->firstContextValue($context?->agentRole, $context?->position)),
            $this->labeledBlock('جهت تماس', $this->formatCallDirection($context?->callDirection)),
        ];

        $additional = $this->additionalContextLines($context);
        if ($additional !== []) {
            $sections[] = "زمینه تکمیلی:\n".implode("\n", $additional);
        }

        $transcript = $this->contextValue($context?->transcript, 'فایل صوتی پیوست شده است و متن جداگانه‌ای در دست نیست.');
        $sections[] = $this->labeledBlock('متن مکالمه', $transcript);
        $sections[] = $this->labeledBlock(
            'وظیفه',
            "به فایل صوتی پیوست‌شده گوش دهید و مکالمه را تحلیل کنید.\nخلاصه باید مفصل، کسب‌وکاری و کاملاً فارسی باشد.\nفقط خروجی ساخت‌یافته با کلیدهای خواسته‌شده را برگردانید؛ همه مقدارهای متنی فارسی باشند.",
        );

        return implode("\n\n", $sections);
    }

    private function resolveBasePrompt(?string $version): string
    {
        if (is_string($version) && $version !== '' && $version !== 'v1') {
            $prompt = LlmPromptVersion::query()
                ->where('version', $version)
                ->where('is_active', true)
                ->first();

            if ($prompt) {
                return $prompt->system_prompt;
            }
        }

        return $this->defaultSystemPrompt();
    }

    private function labeledBlock(string $label, string $value): string
    {
        return "{$label}:\n{$value}";
    }

    private function contextValue(?string $value, string $default = 'نامشخص'): string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : $default;
    }

    private function firstContextValue(?string ...$values): string
    {
        foreach ($values as $value) {
            $trimmed = trim((string) $value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return 'نامشخص';
    }

    private function formatCallDirection(?string $direction): string
    {
        $normalized = strtolower(trim((string) $direction));
        $enum = CallDirection::tryFrom($normalized);

        if ($enum === null) {
            $enum = match ($normalized) {
                'in', 'incoming', 'inbound call' => CallDirection::Inbound,
                'out', 'outgoing', 'outbound call' => CallDirection::Outbound,
                default => null,
            };
        }

        return $enum?->analysisPromptLabel() ?? $this->contextValue($direction);
    }

    /** @return list<string> */
    private function additionalContextLines(?PromptContextData $context): array
    {
        $meta = [];

        if ($context?->organizationBusinessContext) {
            $businessContext = trim($context->organizationBusinessContext);
            if ($businessContext !== '') {
                $meta[] = "زمینه فعالیت سازمان: {$businessContext}";
            }
        }
        if ($context?->department) {
            $meta[] = "دپارتمان: {$context->department}";
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
        if ($context?->callDurationSeconds) {
            $meta[] = "مدت تماس: {$context->callDurationSeconds} ثانیه";
        }
        if ($context?->notes) {
            $meta[] = "یادداشت‌ها: {$context->notes}";
        }

        $crmLines = [];
        $employeeName = trim((string) $context?->employeeName);
        $organizationName = trim((string) $context?->organizationName);

        if ($employeeName !== '') {
            $crmLines[] = "نام کارشناس فعلی سامانه: {$employeeName}";
        }
        if ($organizationName !== '') {
            $crmLines[] = "نام سازمان فعلی سامانه: {$organizationName}";
        }

        if ($crmLines !== []) {
            $meta[] = "زمینه سامانه (این مقدارها هویت مشتری نیستند):\n".implode("\n", $crmLines);
        }

        return $meta;
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
