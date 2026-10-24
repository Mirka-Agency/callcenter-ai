<?php

namespace App\Application\Llm\Prompts;

use App\Domain\Coaching\CoachingCatalog;
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
- هر نقطه ضعف یا انتقاد باید مستند به رفتار یا جمله مشخص و قابل مشاهده در همین مکالمه باشد؛ بدون شاهد روشن در مکالمه، انتقاد کلی ننویسید و در صورت نبود ضعف واقعی، آرایه weaknesses را خالی بگذارید
- فقط موارد زیر را در صورت وجود شاهد مشخص به‌عنوان نقطه ضعف ثبت کنید:
  - گفتار نامفهوم یا مبهم
  - ارتباط نادرست یا ضعیف با مشتری
  - رعایت نکردن مراحل صحیح فرآیند تماس
  - رفتار نامناسب در برخورد با مشتری
  - خطاهای واقعی در ارائه اطلاعات یا خدمات
- جواب ندادن مشتری، بوق، پیام اپراتور، صندوق صوتی، یا شکل نگرفتن مکالمه نقطه ضعف کارشناس نیست
- اگر طرف مقابل گفته باید با داخلی، بخش یا شخص دیگری تماس گرفته شود و تماس همان‌جا قطع شده، این نقطه ضعف «عدم پیگیری» نیست. اگر قول صریح تماس برگشتی با زمان مشخص داده نشده، اقدام بعدی را فقط در next_actions ثبت کنید
PROMPT;
    }

    public static function summaryPolicy(): string
    {
        return <<<'PROMPT'
قوانین تولید خلاصه:
- یک خلاصه کسب‌وکاری مفصل با نثر فارسی روان و رعایت قانون زبان بنویسید؛ یک تا سه پاراگراف (حدود ۸۰ تا ۱۸۰ کلمه)، با روایت طبیعی و بدون تکرار یا فهرست گلوله‌ای
- پوشش دهید: دلیل اصلی تماس، درخواست و سوالات مهم مشتری، پاسخ‌های کلیدی کارشناس، دغدغه‌ها یا اعتراضات مهم، نتیجه کلی مکالمه، اقدامات بعدی توافق‌شده و نیازهای پیگیری
- بین اقدام توافق‌شده در مکالمه و پیشنهاد بهبود خودتان تفکیک قائل شوید؛ در خلاصه و next_actions فقط کارهایی را بنویسید که واقعاً در تماس توافق شده یا ادامه مستقیم درخواست باز مشتری است، و توصیه‌های آموزشی را به coaching_analysis یا missed_opportunities بسپارید
- اگر نام شخص یا شرکت نامطمئن و تأییدنشده است، نام حدسی ننویسید و از «مشتری» یا «کارشناس» استفاده کنید
- متن مکالمه را تکرار نکنید؛ اطلاعات را بدون جزئیات بی‌اهمیت برای تصمیم‌گیری مدیریتی و فروش فشرده کنید تا خواننده بدون خواندن متن کامل، جریان تماس را درک کند
PROMPT;
    }

    public static function speakerRolePolicy(): string
    {
        return <<<'PROMPT'
تفکیک کارشناس و مشتری، قبل از خلاصه و هویت:
- برچسب خط را عوض نکن. «کارشناس:» مشتری نیست و «مشتری:» کارشناس نیست.
- نام کارشناس فعلی و نام سازمان فعلی همیشه طرف کارشناس است، حتی اگر در سلام گفته شوند.
- ورودی: پاسخ‌دهنده که سازمان را معرفی می‌کند کارشناس است. خروجی: تماس‌گیرنده که سازمان را معرفی می‌کند کارشناس است.
- پیگیری پول: کسی که از طرف سازمان طلب را می‌خواهد کارشناس است، حتی اگر جهت تماس اشتباه ثبت شده باشد.
- «من فلان هستم» فقط به همان گوینده تعلق دارد.
PROMPT;
    }

    public static function customerIdentityPolicy(): string
    {
        return <<<'PROMPT'
قوانین استخراج هویت مشتری و قوانین تفکیک کارشناس، مشتری و شرکت (الزامی):
- نام کارشناس فعلی و نام سازمان فعلی متعلق به سازمان استفاده‌کننده از سامانه است؛ این مقدارها را هویت مشتری ندانید مگر اینکه مکالمه صریحاً خلاف آن را ثابت کند
- customer_identity فقط از گفته خود مشتری پر شود:
  - person_name را فقط وقتی پر کنید که خود مشتری نامش را گفته باشد، یا کارشناس او را صدا زده و مشتری تأیید کرده باشد. نام کارشناس، «آقا/خانم/مهندس» یا معرفی کارشناس را در person_name ننویسید
  - company_name فقط شرکت، برند یا محل کار خود مشتری است (با املای تأییدشده همان نام و مطابق قانون زبان؛ ي و ك عربی به ی و ک تبدیل شود و «شرکت شرکت» تکرار نشود). در تماس خروجی یا ورودی، معرفی سازمان توسط کارشناس نام شرکت مشتری نیست
  - email و phone_number و job_title فقط اگر خود مشتری گفته باشد نوشته شوند. سمت کارشناس، سمت مشتری نیست
  - evidence بگوید کدام گوینده گفته؛ مثلاً «مشتری گفت: از شرکت سپهر هستم»
- حدس نزنید. دو نام مبهم یا نام تأییدنشده یعنی فیلد خالی و اشاره عمومی با «مشتری» در متن خلاصه
- کسی که از طرف سازمان می‌گوید طرف مقابل پرداخت نکرده، کارشناس است؛ حتی اگر جهت تماس اشتباهی ورودی ثبت شده باشد. در خلاصه بنویسید کارشناس برای پیگیری پرداخت با مشتری تماس گرفت
- هویت فقط برای سازمان فعلی این تماس معتبر است. شماره تلفن به‌تنهایی مشتری سازمان دیگر نیست
PROMPT;
    }

    public static function leadAnalysisPolicy(): string
    {
        return <<<'PROMPT'
ارزیابی کیفیت لید و دغدغه‌ها (الزامی):
- کیفیت لید مشتری (lead_quality) و دغدغه‌ها/اعتراضات (concerns) را با نگاه فروش و تبدیل مشتری، بر اساس احتمال خرید، فوریت، بودجه، جدیت پرسش و نشانه‌های صریح یا ضمنی تمایل به خرید استخراج کنید
- کیفیت لید فقط تمایل مشتریِ سازمان به خریدن محصول یا خدمت خود سازمان است
- اگر کارشناس خریدار است و از شخص، فروشگاه، رستوران یا شرکت دیگری کالا یا خدمت می‌خرد، این فرصت معاملاتی نیست؛ در این حالت lead_quality.score و purchase_probability را صفر، level را low، و reason و buying_intent_signals را خالی بگذارید
- level را فقط وقتی high بگذارید که مشتری سازمان درباره خرید جدیت روشن نشان دهد (مثل درخواست پیش‌فاکتور، تأیید بودجه یا توافق خرید). پشتیبانی، پیگیری مطالبه، هماهنگی داخلی و خرید خود کارشناس را high نگذارید
PROMPT;
    }

    public static function sentimentPolicy(): string
    {
        return <<<'PROMPT'
قوانین دسته‌بندی احساس مشتری (الزامی):
- فیلدهای sentiment و customer_insights.sentiment باید یکسان باشند و حال‌وهوای عاطفی خود مشتری در همین تماس را نشان دهند (نه لحن یا امتیاز کارشناس)
- مقدار neutral نمونه خروجی را کپی نکنید؛ مکالمه را فقط به‌خاطر معمولی بودن فروش یا پشتیبانی خنثی نکنید:
  - positive: مشتری راضی، سپاسگزار، امیدوار یا در پایان تماس آسوده‌خاطر است (مثل «ممنون، مشکل حل شد»)
  - negative: مشتری ناراضی، عصبانی یا معترض است؛ اگر مشتری اعتراضی نسبت به محصول یا برند ما دارد، حتماً negative بگذارید و ناراحتی واقعی از عملکرد کارشناس negative است (مثل «محصول خراب است و سه بار زنگ زدم جواب ندادید»)
  - mixed: هم نشانه روشن رضایت و هم نشانه روشن نارضایتی در همین تماس وجود دارد (مثل «خود محصول خوب است ولی ارسال خیلی دیر شد و ناراحتم»)
  - neutral: هیچ نشانه روشنی از رضایت یا نارضایتی نیست؛ مثل استعلام خشک قیمت («قیمت طرح سالانه چقدر است؟») یا هماهنگی کوتاه
- استثنا در پیگیری پرداخت‌نشده و بدهی: اگر کارشناس برای مطالبه زنگ زده، لحن جدی کارشناس یا بهانه مشتری منفی نیست (اگر نقش کارشناس و مشتری را عوض کنید، تماس اشتباهی در مشتریان ناراضی می‌رود). فقط وقتی negative بگذارید که خود مشتری جدا از بدهی، به برند، محصول یا خدمات اعتراض کند. مشکل خط یا شکایت از رقیب احساس منفی به سازمان ما نیست
PROMPT;
    }

    public static function attentionPolicy(): string
    {
        return <<<'PROMPT'
تماس‌های نیازمند توجه (الزامی):
- اگر مشتری اعتراض یا شکایت مهمی دارد که مدیریت باید بداند (اعتراض به عملکرد کارشناس، اعتراض به محصول، اعتراض به سرویس یا خدمات، اعتراض کلی، تهدید به قطع همکاری یا درخواست صحبت با مدیر)، needs_attention.needed را درست بگذارید، دسته‌ها را از agent ، product ، service ، general ، other انتخاب کنید و دلیل را در reason بنویسید
- پرسش عادی قیمت یا زمان تحویل بدون نارضایتی جدی نیازمند توجه نیست؛ در این صورت needed را نادرست، categories را آرایه خالی و reason را رشته خالی بگذارید
PROMPT;
    }

    public static function followUpPolicy(): string
    {
        return <<<'PROMPT'
قوانین پیشنهاد پیگیری تلفنی و فهرست پیگیری فراموش‌شده (الزامی):
- فیلد follow_up_suggestions فقط برای قول صریح تماس تلفنی برگشتی (فقط قول صریح خود کارشناس) با همین مشتری در زمان مشخص است؛ اگر قولی نداده یا زمانی در مکالمه گفته نشده، آرایه خالی بگذارید و موعد را حدس نزنید
- هر سه شرط باید برقرار باشد: درخواست مشتری در همین تماس باز مانده، کارشناس صریحاً قول داده یا با مشتری قرار گذاشته دوباره با همان مشتری تلفنی تماس بگیرد، و اقدام اصلی همان زنگ زدن مجدد است (مثلاً «تماس پیگیری فردا برای اعلام نتیجه بررسی»)
- زمان قول را فقط با واژه شنیده‌شده در مکالمه بیاورید (امروز، فردا، پس‌فردا، روز بعد، هفته آینده، یا تعداد روز مثل «۳ روز دیگر»)
- این موارد قول تماس کارشناس نیستند و نباید در follow_up_suggestions بیایند (در صورت نیاز فقط در next_actions بیایند):
  - ارسال فایل، سند، کاتالوگ، پیش‌فاکتور، قرارداد یا لینک
  - پیام در واتساپ، تلگرام، اینستاگرام، پیامک یا ایمیل
  - ثبت تیکت، پیگیری مشکل سیستمی، هماهنگی داخلی، دمو آنلاین، یا تماس با همکار و واحد داخلی
  - وقتی مشتری گفت خودش زنگ می‌زند، یا کارشناس گفت «اگر لازم شد تماس بگیرید»، یا موضوع در همین تماس بسته شد
- همان قول تماس را در next_actions تکرار نکنید و در next_actions و missed_opportunities عبارت «تماس پیگیری»، «تماس مجدد» یا «زنگ زدن» ننویسید؛ اگر پیگیری لازم بوده ولی قول داده نشده، فقط در missed_opportunities بدون واژه تماس بنویسید

قاعده ارجاع به داخلی یا بخش دیگر همان شرکت (الزامی):
- اگر پاسخ‌دهنده بگوید باید با داخلی، بخش یا شخص دیگری در همان شرکت تماس گرفته شود و مکالمه تمام شود، این مورد را در weaknesses و missed_opportunities ننویسید
- اگر قول صریح تماس برگشتی با زمان مشخص داده نشده، follow_up_suggestions را خالی بگذارید و در next_actions بنویسید: «پیگیری از طریق بخش یا داخلی معرفی‌شده»
PROMPT;
    }

    public static function evaluableConversationPolicy(): string
    {
        return <<<'PROMPT'
ارزیابی‌پذیری مکالمه (الزامی):
- اگر مشتری یا طرف مقابل اصلاً پاسخ نداده، فقط بوق، پیام اپراتور، صندوق صوتی یا موسیقی شنیده شده، یا مکالمه دوطرفه معناداری شکل نگرفته، evaluable را نادرست و score را صفر بگذارید
- در این حالت «عدم ارتباط با مشتری» نقطه ضعف نیست؛ weaknesses ، strengths ، follow_up_suggestions و next_actions را آرایه خالی بگذارید، در خلاصه بنویسید مکالمه قابل ارزیابی نبود و لید واقعی ثبت نکنید
- امتیاز صفر یعنی «قابل ارزیابی نیست»؛ مکالمه واقعی ضعیف را بین ۱ تا ۴۰ امتیاز دهید، نه صفر
PROMPT;
    }

    public static function personalCallPolicy(): string
    {
        return <<<'PROMPT'
تشخیص تماس شخصی (الزامی و مقدم بر خلاصه مفصل، زمینه فعالیت سازمان، امتیاز، مهارت و هویت مشتری):
- اگر کارشناس در این تماس مشتریِ طرف مقابل است و خریدن چیزی برای خودش یا کار شخصی انجام می‌دهد (مثل سفارش غذا، رستوران، کافه، خرید شخصی از فروشگاه یا شرکت دیگر، یا صحبت با خانواده و دوست بدون موضوع کاری)، تماس شخصی است
- اگر کارشناس خریدار است و معلوم نیست کالا برای سازمان است یا برای خودش، is_personal را درست بگذارید؛ زمینه فعالیت سازمان را روی این تماس‌ها اعمال نکنید
- اگر حتی بخشی از مکالمه فروش یا پشتیبانی خود سازمان به مشتری سازمان است (یا پیگیری مطالبات مشتری، هماهنگی کاری یا خرید صریح برای نیاز سازمان است)، is_personal را نادرست و personal_reason را رشته خالی بگذارید
- وقتی is_personal درست است: personal_reason یک جمله کوتاه بدون جزئیات خصوصی باشد، summary فقط یک تا دو جمله درباره شخصی بودن تماس باشد، evaluable نادرست، score و lead_quality.score صفر، sentiment برابر neutral، needs_attention.needed نادرست، coaching_analysis را تهی بگذارید، customer_identity را خالی بگذارید و آرایه‌های strengths ، weaknesses ، next_actions ، concerns ، follow_up_suggestions و missed_opportunities خالی باشند
PROMPT;
    }

    public static function persianLanguagePolicy(): string
    {
        return <<<'PROMPT'
قانون زبان (الزامی و مقدم بر هر دستور دیگر):
- مخاطب خروجی فارسی‌زبان است. نثر توضیحی، خلاصه، ارزیابی، بازخورد و پیشنهادها را به فارسی روان بنویسید؛ مجاز بودن واژه انگلیسی به معنی نوشتن توضیح کامل به انگلیسی نیست
- واژه‌های انگلیسی، اصطلاحات فنی، نام اشخاص، برندها و محصولات را فقط وقتی در متن مکالمه یا فراداده معتبر آمده‌اند، با املای اصلی و قابل تشخیص حفظ کنید. آن‌ها را به اجبار ترجمه یا فارسی‌نویسی آوایی نکنید
- ایمیل، نشانی وب، شناسه‌ها، شماره‌ها، نام مدل و مقدار skill_key را ترجمه یا فارسی‌نویسی نکنید. نام کلیدهای خروجی و کدهای قراردادی را عوض نکنید
- نقل‌قول شاهد را با همان واژه‌ها و زبان منبع حفظ کنید؛ توضیح شاهد را به فارسی بنویسید. ترجمه را به‌جای نقل‌قول مستقیم ننویسید
- املای مبهم، نام نامطمئن یا اصطلاح تازه حدس نزنید و شاهد نسازید. این مجوز، قوانین استخراج هویت را تغییر نمی‌دهد؛ نام سازمان و کارشناس زمینه را هویت مشتری ندانید
- هرجا در ساختار خروجی «رشته فارسی» یا «متن فارسی» آمده، منظور نثر فارسی با همین موارد مجاز است
- کدهای بسته (مانند احساس: positive یا neutral یا negative یا mixed و سایر مقادیر محدود در طرحواره) باید دقیقاً با همان املای انگلیسی بیایند
PROMPT;
    }

    public static function persianOutputSample(): string
    {
        return <<<'PROMPT'
نمونه خروجی درست (ساختار و زبان را از همین الگو پیروی کنید؛ محتوا را از مکالمه فعلی بسازید. مقدار sentiment این نمونه را کپی نکنید؛ این مثال فقط چون مشتری بدون تعریف یا شکایت قیمت پرسیده خنثی است):
{
  "score": 78,
  "evaluable": true,
  "is_personal": false,
  "personal_reason": "",
  "summary": "مشتری برای استعلام هزینه تمدید اشتراک تماس گرفت. کارشناس خدمات را توضیح داد، نگرانی قیمت را شنید و پیشنهاد تخفیف تمدید سالانه را مطرح کرد. کارشناس قول داد پیش‌فاکتور را امروز بفرستد و فردا خودش برای تصمیم نهایی با مشتری تماس بگیرد.",
  "sentiment": "neutral",
  "overall_evaluation": "کارشناس مؤدب و مسلط بود، اما دعوت به تصمیم خرید را کمی دیر شروع کرد.",
  "strengths": ["برخورد محترمانه و پاسخ‌گویی شفاف", "توضیح شفاف خدمات"],
  "weaknesses": ["دعوت مستقیم به تصمیم خرید کمی دیر انجام شد"],
  "next_actions": ["ارسال پیش‌فاکتور در همین روز"],
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
    "email": "",
    "job_title": "",
    "phone_number": "",
    "confidence": 0,
    "evidence": ""
  },
  "coaching_analysis": {
    "skills": [
      {
        "skill_key": "need_discovery",
        "score": 54,
        "status": "needs_improvement",
        "confidence": 0.8,
        "evidence": [
          {
            "description": "پیش از فهم نیاز، معرفی محصول شروع شد",
            "quote_or_summary": "کارشناس بلافاصله طرح سالانه را توضیح داد",
            "start_time": null,
            "end_time": null
          }
        ],
        "feedback": "کارشناس قبل از روشن شدن بودجه، ارائه را شروع کرد.",
        "recommendation": "پیش از معرفی طرح، دو پرسش درباره کاربرد و بودجه بپرسد."
      }
    ],
    "strengths": [],
    "weaknesses": [],
    "coaching_recommendations": []
  }
}
PROMPT;
    }

    public static function persianStrictRetryPolicy(): string
    {
        return <<<'PROMPT'
نثر توضیحی خروجی را به فارسی روان بنویسید و از توضیح کامل به انگلیسی خودداری کنید.
موارد مجاز قانون زبان، از جمله واژه‌های انگلیسی منبع، نام‌ها، اصطلاحات فنی و اطلاعات تماس را با املای اصلی حفظ کنید.
نقل‌قول مستقیم را ترجمه نکنید. نام کلیدها، شناسه‌ها و کدهای قراردادی را عوض نکنید.
PROMPT;
    }

    public function defaultSystemPrompt(): string
    {
        return <<<'PROMPT'
شما تحلیل‌گر حرفه‌ای کیفیت تماس در مرکز تماس هستید. مکالمه را تحلیل کنید و فقط یک شیء JSON معتبر با کلیدهای دقیق زیر برگردانید. نثر توضیحی را به فارسی روان و مطابق قانون زبان بنویسید.

- score (عدد صحیح صفر تا صد، امتیاز کلی عملکرد کارشناس)
- evaluable (درست اگر مکالمه واقعی و قابل ارزیابی است؛ نادرست اگر تماس برقرار نشده، مشتری پاسخ نداده، صحبت معناداری نشد، یا تماس شخصی است)
- is_personal (درست اگر کارشناس از خط شرکت برای کار شخصی استفاده کرده؛ از جمله سفارش غذا یا خریدن چیزی برای خودش از شرکت دیگر. نادرست اگر موضوع، مشتری یا کار خود سازمان است)
- personal_reason (رشته فارسی — اگر is_personal درست است یک جمله کوتاه درباره نوع موضوع شخصی؛ وگرنه رشته خالی)
- summary (رشته فارسی — خلاصه کسب‌وکاری؛ یک تا سه پاراگراف و حدود هشتاد تا صد و هشتاد کلمه. هر نکته را فقط یک‌بار بنویسید)
- sentiment (فقط یکی از: positive ، neutral ، negative ، mixed — حال‌وهوای عاطفی خود مشتری در همین تماس؛ رضایت روشن positive، نارضایتی روشن negative، هر دو mixed، و فقط نبود احساس neutral. مقدار نمونه را کپی نکنید)
- overall_evaluation (رشته فارسی، ارزیابی کوتاه از عملکرد)
- strengths (آرایه‌ای از رشته‌های فارسی — نقاط قوت)
- weaknesses (آرایه‌ای از رشته‌های فارسی — فقط نقاط ضعف رفتاری و ارتباطی واقعی؛ هرگز واژه فنی لاتین یا اصطلاح تخصصی را نقطه ضعف ندانید)
- next_actions (آرایه‌ای از رشته‌های فارسی — فقط کارهای غیرتلفنی مثل ارسال فایل، ثبت تیکت یا پیگیری از طریق بخش یا داخلی معرفی‌شده؛ قول تماس مجدد را اینجا ننویسید)
- model (رشته)
- performance_dimensions (شیء با امتیاز صفر تا صد برای هر کلید؛ امتیازدهی متناسب با نوع تماس باشد و تماس پشتیبانی یا هماهنگی به‌خاطر نبود ارائه فروش جریمه نشود):
  - communication_skills
  - product_knowledge
  - objection_handling (در صورت نبود اعتراض، بر اساس شفافیت پاسخ‌گویی سنجیده شود و جریمه نشود)
  - closing_ability (در فروش: هدایت به گام بعدی خرید؛ در پشتیبانی، مالی یا هماهنگی: جمع‌بندی شفاف راه‌حل و اقدام بعدی)
  - professionalism
- customer_insights (شیء):
  - sentiment (همان مقدار فیلد sentiment بالا؛ حال‌وهوای عاطفی خود مشتری)
  - intent (رشته فارسی — خواسته مشتری)
  - purchase_probability (عدد صفر تا صد)
  - urgency_level (فقط یکی از: low ، medium ، high ، critical)
  - risk_level (فقط یکی از: low ، medium ، high)
- operational_insights (شیء):
  - missed_opportunities (آرایه رشته‌های فارسی)
  - escalation_risks (آرایه رشته‌های فارسی)
  - compliance_issues (آرایه رشته‌های فارسی)
  - important_keywords (آرایه رشته‌های فارسی)
  - follow_up_suggestions (آرایه رشته‌های فارسی — فقط قول صریح کارشناس برای تماس تلفنی برگشتی با همین مشتری، همراه با زمان گفته‌شده در مکالمه؛ اگر قولی نداده یا زمانی گفته نشده آرایه خالی بماند. پیشنهاد خودتان یا ارجاع بدون قول را ننویسید)
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
  - person_name (رشته فارسی — نام خود مشتری، نه نام کارشناس زمینه تماس)
  - company_name (رشته فارسی — شرکت خود مشتری، نه نام سازمان سامانه)
  - email (رشته — فقط اگر خود مشتری گفته باشد)
  - job_title (رشته فارسی — سمت خود مشتری)
  - phone_number (رشته — فقط اگر خود مشتری گفته باشد)
  - confidence (عدد اعشاری صفر تا یک)
  - evidence (رشته فارسی — جمله استخراج‌شده از مکالمه)
- coaching_analysis (شیء یا تهی — ارزیابی مهارت کارشناس؛ اگر مکالمه قابل ارزیابی نیست تهی باشد)

منصفانه، سازنده و دقیق باشید. روی مهارت ارتباطی، حل مسئله، همدلی، انطباق و فرصت‌های فروش تمرکز کنید.
PROMPT;
    }

    public static function transcriptAnalysisPolicy(): string
    {
        return <<<'PROMPT'
منبع این تحلیل فقط متن مکالمه است. فایل صوتی پیوست نشده است.
دستور گوش‌دادن به فایل صوتی را نادیده بگیرید.
فقط همان متن را تحلیل کنید. جمله‌ای که در متن نیست به مکالمه اضافه نکنید و گفتار نسازید.
ویژگی‌های صوتی که در متن قابل مشاهده نیستند (مانند تن صدا، سرعت صحبت، بلندی صدا، مکث‌های صوتی یا لحن آوایی) را قضاوت، تحسین یا نقد نکنید؛ ارزیابی و احساس را فقط بر پایه واژه‌ها، جمله‌بندی و محتوای صریح متن انجام دهید.
بوق، زنگ، موسیقی انتظار، آهنگ بین وصل شدن به داخلی دیگر، و سکوت گفتار نیستند. اگر نشانه‌ای از آن‌ها در متن مانده، آن را حرف کارشناس یا مشتری حساب نکنید و از آن خلاصه، امتیاز، احساس، موضوع یا ضعف نسازید.
انتقال تماس به داخلی دیگر و فاصله بین دو بخش مکالمه، خودش مشکل عملکرد نیست. فقط حرف‌های واقعی افراد را تحلیل کنید.
PROMPT;
    }

    public function systemPrompt(?string $version = null): string
    {
        $base = $this->resolveBasePrompt($version);

        return implode("\n\n", [
            self::persianLanguagePolicy(),
            self::speakerRolePolicy(),
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
            self::personalCallPolicy(),
            self::coachingPolicy(),
        ]);
    }

    public static function coachingPolicy(): string
    {
        $skills = collect(CoachingCatalog::skills())
            ->map(fn (array $skill) => "  - {$skill['key']} ({$skill['label']})")
            ->implode("\n");

        return <<<PROMPT
قوانین ارزیابی مهارت کارشناس (الزامی):
- فقط رفتار کارشناس را ارزیابی کنید. لحن، اعتراض یا تردید مشتری مهارت کارشناس نیست
- فقط مهارتی را امتیاز دهید که از همین مکالمه قابل استنباط است. حداکثر چهار مهارت در skills. هر مهارت فقط یک شاهد. strengths و weaknesses هر کدام حداکثر دو مورد و coaching_recommendations حداکثر دو مورد
- امتیاز، کیفیت مشاهده‌شده است و اطمینان، میزان اتکای شما به شاهد است. این دو را یکی نکنید
- score عدد صفر تا صد است. confidence عدد اعشاری صفر تا یک است. اگر شاهد ندارید confidence را پایین بگذارید، نه اینکه واقعیت بسازید
- status را فقط از روی امتیاز و دقیقاً یکی از این مقدارها بگذارید: strength ، good ، needs_improvement ، critical
- اگر نقل‌قول یا زمان دقیق در دست نیست، quote_or_summary یا start_time و end_time را تهی بگذارید. زمان را حدس نزنید
- start_time و end_time ثانیه از آغاز تماس هستند، یا تهی
- explanation و recommendation باید به رفتار مشخص همین تماس اشاره کنند
- پیشنهاد کلی و غیرقابل اجرا ممنوع است. مثال بد: «کارشناس باید بهتر ارتباط برقرار کند.» مثال خوب: «کارشناس پیش از روشن شدن تعداد کاربر، بودجه و کاربرد اصلی، ارائه محصول را شروع کرد. پیش از معرفی طرح، دست‌کم دو یا سه پرسش کشف نیاز بپرسد.»
- strengths فقط مهارت‌هایی است که در همین تماس خوب انجام شده‌اند. weaknesses فقط مهارت‌هایی است که شاهد ضعف دارند
- severity و priority فقط یکی از low ، medium ، high
- اگر مکالمه قابل ارزیابی نیست، coaching_analysis را تهی بگذارید و مهارت نسازید
- نثر فیلدهای متنی مطابق قانون زبان فارسی باشد. skill_key را عوض نکنید

کالیبراسیون امتیاز بر اساس نوع تماس و بازه‌های امتیاز (الزامی):
- مهارت‌ها و امتیازها را با نوع واقعی تماس بسنجید:
  - تماس فروش یا استعلام خرید: کشف نیاز (need_discovery)، ارائه (presentation)، مدیریت اعتراض (objection_handling)، مذاکره (negotiation) و بستن فروش (closing) فقط وقتی موضوعیت دارند که مکالمه واقعاً فروش‌محور باشد
  - تماس پشتیبانی، فنی، پیگیری مالی یا هماهنگی: کارشناس را به‌خاطر نداشتن پیشنهاد فروش، مذاکره قیمت یا بستن معامله جریمه نکنید؛ مهارت‌های closing و negotiation را برای تماس غیرفروشی امتیاز ندهید و به‌جای آن مهارت‌های مرتبط مانند active_listening ، product_knowledge ، empathy ، dissatisfied_customer ، follow_up و call_closing (جمع‌بندی راه‌حل و پایان تماس) را بسنجید
- بازه‌های امتیاز و status متناظر با شواهد قابل مشاهده:
  - ۸۵ تا ۱۰۰ (strength): اجرای کامل، دقیق و حرفه‌ای مهارت با شاهد مشخص در مکالمه
  - ۷۰ تا ۸۴ (good): اجرای استاندارد و مؤثر متناسب با نیاز تماس با جای بهبود جزئی
  - ۵۰ تا ۶۹ (needs_improvement): اجرای ناقص، جا انداختن گام مهم یا پاسخ ناکافی که شاهد روشن در مکالمه دارد
  - ۱ تا ۴۹ (critical): خطای جدی، اطلاعات نادرست، برخورد نامناسب یا رها کردن درخواست مشتری

مهارت‌های مجاز:
{$skills}

ساختار coaching_analysis:
- skills (آرایه): skill_key ، score ، status ، confidence ، evidence ، feedback ، recommendation
- evidence (آرایه): description ، quote_or_summary ، start_time ، end_time
- strengths (آرایه): skill_key ، title ، explanation ، evidence
- weaknesses (آرایه): skill_key ، title ، explanation ، severity ، evidence
- coaching_recommendations (آرایه): skill_key ، priority ، recommendation ، suggested_action
PROMPT;
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

        $hasTranscript = trim((string) $context?->transcript) !== '';
        $transcript = $this->contextValue($context?->transcript, 'فایل صوتی پیوست شده است و متن جداگانه‌ای در دست نیست.');
        $sections[] = $this->labeledBlock('متن مکالمه', $transcript);
        $task = $hasTranscript
            ? "فقط متن مکالمه بالا را تحلیل کنید. فایل صوتی پیوست نشده است و نباید گفتار تازه‌ای ساخته شود.\n"
            : "به فایل صوتی پیوست‌شده گوش دهید و مکالمه را تحلیل کنید.\n";
        $sections[] = $this->labeledBlock(
            'وظیفه',
            $task."خلاصه باید مفصل، کسب‌وکاری و با نثر فارسی مطابق قانون زبان باشد.\nبرچسب کارشناس و مشتری را عوض نکن. نام کارشناس و نام سازمان زمینه تماس هویت مشتری نیست.\nفقط یک شیء JSON معتبر برگردان و هر نکته را فقط یک‌بار بنویس.",
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

    public function systemPromptFor(AudioAnalysisRequestData $request): string
    {
        $prompt = $this->systemPrompt($request->promptVersion);

        if (trim((string) $request->context?->transcript) !== '') {
            $prompt .= "\n\n".self::transcriptAnalysisPolicy();
        }

        return $prompt;
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
            $crmLines[] = 'این شخص کارشناس سازمان است و نام مشتری نیست؛ حتی اگر در مکالمه خودش را معرفی کند.';
        }
        if ($organizationName !== '') {
            $crmLines[] = "نام سازمان فعلی سامانه: {$organizationName}";
            $crmLines[] = 'این نام شرکت ماست و نام شرکت مشتری نیست؛ حتی اگر کارشناس در معرفی آن را بگوید.';
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
        $systemPrompt = $this->systemPromptFor($request);

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
