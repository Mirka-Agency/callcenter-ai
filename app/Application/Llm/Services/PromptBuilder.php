<?php

namespace App\Application\Llm\Services;

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
- فقط موارد زیر را به‌عنوان نقطه ضعف ثبت کنید:
  - گفتار نامفهوم یا مبهم
  - ارتباط نادرست یا ضعیف با مشتری
  - رعایت نکردن مراحل صحیح فرآیند تماس
  - رفتار نامناسب در برخورد با مشتری
  - خطاهای واقعی در ارائه اطلاعات یا خدمات
- جواب ندادن مشتری، بوق، پیام اپراتور، صندوق صوتی، یا شکل نگرفتن مکالمه نقطه ضعف کارشناس نیست
- اگر طرف مقابل گفته باید با داخلی، بخش یا شخص دیگری تماس گرفته شود و تماس همان‌جا قطع شده، این نقطه ضعف «عدم پیگیری» نیست. پیگیری بعد از همین تماس است و فقط در follow_up_suggestions ثبت می‌شود
PROMPT;
    }

    public static function summaryPolicy(): string
    {
        return <<<'PROMPT'
قوانین تولید خلاصه:
- یک خلاصه کسب‌وکاری مفصل، فقط به فارسی بنویسید؛ نه انگلیسی، نه چند جمله کوتاه
- معمولاً یک تا سه پاراگراف و حدود هشتاد تا صد و هشتاد کلمه باشد. نکته را تکرار نکنید
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
قوانین استخراج هویت مشتری:
- نام کارشناس فعلی و نام سازمان فعلی متعلق به فروشنده یا سازمان استفاده‌کننده از سامانه است
- این مقدارها را هویت مشتری ندانید مگر اینکه مکالمه صریحاً خلاف آن را ثابت کند
- customer_identity فقط از گفته خود مشتری پر شود:
  - person_name و company_name و email و job_title و phone_number و confidence و evidence
- person_name را فقط وقتی پر کنید که خود مشتری نامش را گفته باشد، یا کارشناس او را صدا زده و مشتری تأیید کرده باشد. اگر فقط کارشناس نامی را گفت، فیلد را خالی و confidence را پایین بگذارید
- company_name فقط شرکت، برند یا محل کار خود مشتری است. در تماس خروجی معرفی سازمان توسط کارشناس نام شرکت مشتری نیست. company_name فقط وقتی پر شود که مشتری صریحاً نام شرکت خودش را گفته باشد
- نام شرکت را با املای درست فارسی بنویسید. ي و ك عربی را به ی و ک تبدیل کنید و «شرکت شرکت» را تکرار نکنید
- email و phone_number و job_title را فقط اگر خود مشتری گفته باشد بنویسید. سمت کارشناس، سمت مشتری نیست
- حدس نزنید. دو نام مبهم یعنی فیلد خالی

قوانین تفکیک کارشناس، مشتری و شرکت (الزامی):
- نام کارشناس را در person_name یا company_name یا job_title ننویسید. «آقا» و «خانم» و «مهندس» یا تفاوت تلفظ، نام کارشناس را به نام مشتری تبدیل نمی‌کند. معرفی دیگرِ خود کارشناس هم person_name مشتری نیست
- نام سازمان شرکت ماست، حتی اگر کارشناس در سلام آن را بگوید
- در تماس ورودی، پاسخ‌دهنده کارشناس است. در تماس خروجی، تماس‌گیرنده کارشناس است
- کسی که از طرف سازمان می‌گوید طرف مقابل پرداخت نکرده، کارشناس است؛ حتی اگر جهت تماس اشتباهی ورودی ثبت شده باشد. پیگیری پرداخت اعتراض مشتری نیست. در خلاصه بنویسید کارشناس برای پیگیری پرداخت با مشتری تماس گرفت
- evidence بگوید کدام گوینده گفته؛ مثلاً «مشتری گفت: از شرکت سپهر هستم»
- هویت فقط برای سازمان فعلی این تماس معتبر است. شماره تلفن به‌تنهایی مشتری سازمان دیگر نیست
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

نقش خریدار، قبل از هر امتیاز لید (الزامی):
- کیفیت لید فقط تمایل مشتریِ سازمان به خریدن محصول یا خدمت خود سازمان است
- اول مشخص کنید چه کسی خریدار است. اگر کارشناس خریدار است و از شخص، فروشگاه، رستوران یا شرکت دیگری کالا یا خدمت می‌خرد، این فرصت معاملاتی نیست
- در این حالت lead_quality.score را صفر، level را low، reason را خالی و buying_intent_signals را خالی بگذارید. purchase_probability را هم صفر بگذارید
- قیمت، فاکتور، پرداخت، تخفیف، ارسال یا نام کالا در تماسی که کارشناس خریدار است، نشانه فروش سازمان نیست
- level را فقط وقتی high بگذارید که مشتری سازمان درباره خریدن محصول یا خدمت خود سازمان جدیت نشان دهد؛ مثل درخواست پیش‌فاکتور، تأیید بودجه، یا توافق برای بستن معامله
- پشتیبانی، پیگیری مطالبه، هماهنگی داخلی و خرید خود کارشناس را high نگذارید
PROMPT;
    }

    public static function sentimentPolicy(): string
    {
        return <<<'PROMPT'
قوانین دسته‌بندی احساس مشتری (الزامی):
- فیلدهای sentiment و customer_insights.sentiment باید یکسان باشند
- این فیلد حال‌وهوای عاطفی خود مشتری در همین تماس است. آن را از حرف و لحن مشتری بردارید، نه از لحن کارشناس و نه از امتیاز عملکرد کارشناس
- مقدار neutral نمونه خروجی را کپی نکنید. برای هر تماس جداگانه یکی از چهار مقدار را انتخاب کنید
- positive وقتی مشتری راضی، سپاسگزار، امیدوار، مشتاق یا در پایان تماس خیالش راحت است. تشکر گرم، تعریف از محصول یا خدمت یا برخورد، موافقت روشن با ادامه، یا برطرف شدن نگرانی همراه با رضایت کافی است. لازم نیست کلمه «راضی‌ام» را بگوید
- negative وقتی مشتری ناراضی، عصبانی، ناامید، معترض یا در حال تهدید به قطع همکاری است. اعتراض به محصول، خدمات، تأخیر، خلف وعده، قیمت به‌عنوان شکایت، یا ناراحتی واقعی از عملکرد کارشناس negative است
- اگر مشتری اعتراضی نسبت به محصول یا برند ما دارد، حتماً negative بگذارید
- mixed فقط وقتی در همین تماس هم نشانه روشن رضایت و هم نشانه روشن نارضایتی وجود دارد
- neutral فقط وقتی هیچ نشانه روشنی از رضایت یا نارضایتی نیست؛ مثل استعلام خشک قیمت، هماهنگی کوتاه، یا پاسخ بله و خیر بدون بار عاطفی. مکالمه را فقط به‌خاطر معمولی بودن فروش یا پشتیبانی خنثی نکنید
- مثال‌ها:
  - «ممنون، مشکل حل شد» → positive
  - «محصول خراب است و سه بار زنگ زدم جواب ندادید» → negative
  - «خود محصول خوب است ولی ارسال خیلی دیر شد و ناراحتم» → mixed
  - «قیمت طرح سالانه چقدر است؟» بدون تعریف یا شکایت → neutral

استثنا — پیگیری پرداخت‌نشده و بدهی:
- اگر کارشناس به‌خاطر پرداخت‌نشدن زنگ زده، لحن جدی کارشناس و بهانه یا تأخیر مشتری منفی نیست
- حرف کارشناس درباره پول نگرفتن را احساس مشتری حساب نکنید. اگر نقش کارشناس و مشتری را عوض کنید، این تماس اشتباهی در مشتریان ناراضی می‌رود
- فقط وقتی negative بگذارید که خود مشتری، جدا از موضوع بدهی، به برند یا محصول یا خدمات اعتراض کرده باشد
- مشکل خط، قطع تماس یا کیفیت صوت احساس نیست
- شکایت از رقیب، بدون اعتراض به برند یا محصول یا خدمات ما، احساس این سازمان نیست
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

تشخیص قول واقعی برای فهرست پیگیری فراموش‌شده (الزامی):
- follow_up_suggestions بعداً ملاک این است که کارشناس پیگیری را فراموش کرده یا نه. فقط قول صریح خود کارشناس برای زنگ زدن دوباره به همین مشتری را بنویسید
- اگر کارشناس قول تماس مجدد نداده، آرایه را خالی بگذارید. پیشنهاد خودتان، فرصت ازدست‌رفته، یا کاری که «بهتر بود انجام شود» را اینجا ننویسید
- فقط وقتی بنویسید که در مکالمه یکی از این‌ها صریح شنیده شود: کارشناس گفت خودش تماس می‌گیرد، قرار تماس برگشتی گذاشته شد، یا مشتری پذیرفت کارشناس در زمان مشخص دوباره زنگ بزند
- این‌ها قول کارشناس نیستند و آرایه باید خالی بماند:
  - مشتری گفت خودش زنگ می‌زند یا خبر می‌دهد
  - کارشناس گفت «اگر لازم شد با ما تماس بگیرید» یا «در خدمتم»
  - فقط ارسال فایل، پیام یا پیش‌فاکتور قول داده شد
  - موضوع در همین تماس بسته شد
  - پیگیری مفید بود ولی هیچ‌کس قول تماس مجدد نداد
  - تماس با همکار، مدیر یا واحد داخلی
- زمان قول را در همان رشته بیاورید و فقط از واژه‌ای استفاده کنید که در مکالمه گفته شده: امروز، فردا، پس‌فردا، روز بعد، هفته آینده، یا تعداد روز مثل «۳ روز دیگر». اگر هیچ زمانی گفته نشد، آرایه را خالی بگذارید و موعد را حدس نزنید
- متن باید بگوید کارشناس با خود مشتری تماس می‌گیرد؛ مثلاً «تماس پیگیری فردا برای اعلام نتیجه بررسی»
- همان قول را در next_actions تکرار نکنید
- در next_actions و missed_opportunities عبارت تماس پیگیری، تماس مجدد، زنگ زدن یا تماس برگشتی ننویسید تا با قول واقعی قاطی نشود
- اگر پیگیری لازم بوده ولی قول داده نشده، فقط در missed_opportunities و بدون واژه تماس بنویسید؛ مثلاً «قرار مشخصی برای بازگشت نتیجه گذاشته نشد»

استثنا — ارجاع به داخلی یا بخش دیگر همان شرکت (الزامی):
- اگر کسی که پاسخ داده می‌گوید باید با داخلی دیگر، بخش دیگر یا شخص دیگری تماس بگیرید و مکالمه همان‌جا تمام می‌شود، این پیگیری کارشناس است نه نقطه ضعف همین تماس
- این حالت با هماهنگی واحد داخلی سازمان خودمان فرق دارد. واحد خودمان را در follow_up_suggestions ننویسید
- حتی اگر کارشناس صریحاً نگفته «خودم زنگ می‌زنم»، یک مورد در follow_up_suggestions بنویسید
- اگر زمان مشخصی گفته نشده، موعد را «۳ روز دیگر» بگذارید. این تنها موردی است که موعد بدون شنیدن زمان مجاز است
- متن را این‌گونه بنویسید: «تماس پیگیری ۳ روز دیگر برای اتصال به بخش یا داخلی معرفی‌شده». اگر زمان دیگری گفته شد، همان را جایگزین «۳ روز دیگر» کنید
- این مورد را در weaknesses و missed_opportunities ننویسید
PROMPT;
    }

    public static function evaluableConversationPolicy(): string
    {
        return <<<'PROMPT'
ارزیابی‌پذیری مکالمه (الزامی):
- اگر تماس پاسخ داده شده ولی مکالمه معناداری رخ نداده (سکوت، فقط بوق یا موسیقی، قطع فوری بدون صحبت کارشناس و مشتری)، evaluable را نادرست بگذارید و score را صفر بگذارید
- اگر مشتری یا طرف مقابل اصلاً پاسخ نداده، فقط بوق یا پیام اپراتور یا صندوق صوتی شنیده شده، یا مکالمه دوطرفه شکل نگرفته، evaluable را نادرست بگذارید
- چنین تماسی مکالمه نیست. آن را مثل عملکرد کارشناس تحلیل نکنید و هرگز «عدم ارتباط با مشتری» را نقطه ضعف ننویسید
- وقتی evaluable نادرست است، weaknesses و strengths و follow_up_suggestions و next_actions باید آرایه خالی باشند
- امتیاز صفر یعنی «قابل ارزیابی نیست»، نه عملکرد ضعیف. مکالمه واقعی ضعیف را بین یک تا چهل امتیاز دهید؛ هرگز برای عملکرد ضعیف صفر ندهید
- وقتی مکالمه قابل ارزیابی نیست، در خلاصه صریحاً بنویسید مکالمه قابل ارزیابی نبود و کیفیت لید را لید واقعی در نظر نگیرید
PROMPT;
    }

    public static function personalCallPolicy(): string
    {
        return <<<'PROMPT'
تشخیص تماس شخصی (الزامی و مقدم بر خلاصه مفصل، زمینه فعالیت سازمان، امتیاز، مهارت و هویت مشتری):
- اول نقش کارشناس را مشخص کنید. اگر کارشناس در این تماس مشتریِ طرف مقابل است و برای خودش کالا یا خدمت می‌خرد، تماس شخصی است؛ حتی اگر طرف مقابل یک شرکت باشد و در مکالمه قیمت، فاکتور، پرداخت یا ارسال گفته شود
- این نمونه‌ها حتماً شخصی‌اند:
  - سفارش غذا، رستوران، کافه، نانوایی، سوپرمارکت یا هر خوردنی و نوشیدنی
  - زنگ زدن به شرکت، فروشگاه یا خدمات دیگری و خریدن چیزی برای خودش؛ مثل لوازم شخصی، پوشاک، بلیت، تعمیر شخصی یا اشتراک شخصی
  - صحبت با خانواده یا دوست، قرار شخصی، پزشک یا مدرسه فرزند، امور بانکی شخصی، گپ دوستانه بدون موضوع کاری
- نام شرکت طرف مقابل را شرکت مشتریِ سازمان ما ندانید. در این تماس‌ها کارشناس خریدار است، نه فروشنده سازمان
- زمینه فعالیت سازمان را روی این تماس‌ها اعمال نکنید. سفارش غذا یا خرید شخصی را به محصول یا خدمت سازمان ترجمه نکنید
- سفارش غذا فقط وقتی کاری است که صریحاً برای جلسه، پرسنل یا خود سازمان گفته شود. در غیر این صورت شخصی است
- خرید از شرکت دیگر فقط وقتی کاری است که کالا یا خدمت صریحاً برای سازمان، انبار، پروژه یا مشتری سازمان باشد. اگر این را نگفته‌اند، خرید را شخصی حساب کنید
- اگر کارشناس خریدار است و معلوم نیست کالا برای سازمان است یا برای خودش، is_personal را درست بگذارید. این تردید را فرصت فروش سازمان حساب نکنید
- تماس با همکار فقط وقتی شخصی است که هیچ هماهنگی کاری در آن نباشد
- این‌ها شخصی نیستند، حتی اگر لحن صمیمی باشد:
  - فروش، پشتیبانی، معرفی یا پیگیری محصول و خدمات خود سازمان به مشتری سازمان
  - پیگیری پرداخت، فاکتور، بدهی یا تحویل مربوط به مشتری سازمان
  - هماهنگی کاری با همکار، مدیر یا واحد دیگر سازمان
  - خرید یا هماهنگی با تأمین‌کننده وقتی صریحاً برای نیاز سازمان، انبار، پروژه یا مشتری سازمان است
  - منابع انسانی، حقوق یا شیفت مربوط به خود سازمان
  - احوال‌پرسی کوتاهی که بعدش موضوع کار سازمان مطرح می‌شود
- اگر حتی بخشی از مکالمه فروش یا پشتیبانی خود سازمان به مشتری سازمان است، is_personal را نادرست بگذارید
- personal_reason فقط یک جمله کوتاه فارسی درباره نوع موضوع است، مثل «سفارش غذا» یا «خرید شخصی از شرکت دیگر». جزئیات خصوصی، بیماری، اختلاف یا محتوای حساس را شرح ندهید
- اگر is_personal نادرست است، personal_reason را رشته خالی بگذارید

وقتی is_personal درست است:
- evaluable را نادرست و score را صفر بگذارید. این تماس عملکرد کارشناس نیست
- strengths و weaknesses و next_actions و concerns را آرایه خالی بگذارید
- follow_up_suggestions و missed_opportunities را خالی بگذارید
- lead_quality.score را صفر، reason را خالی و buying_intent_signals را خالی بگذارید
- customer_identity را خالی بگذارید. طرف مقابل مشتری سازمان نیست
- coaching_analysis را تهی بگذارید
- needs_attention.needed را نادرست بگذارید
- sentiment را neutral بگذارید. احساس مشتری نسبت به برند اینجا معنا ندارد
- summary را یکی دو جمله بنویسید: بگویید تماس شخصی بوده و موضوع کلی چیست. خلاصه صد تا سیصد کلمه‌ای ننویسید و کارشناس را امتیاز ندهید
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
  - وضعیت مهارت: strength یا good یا needs_improvement یا critical
  - اولویت بهبود: low یا medium یا high
- به‌جز این کدها و نام کلیدها، هیچ حرف لاتین در متن نیاید
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
  "strengths": ["لحن آرام و محترمانه", "توضیح شفاف خدمات"],
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
هشدار: خروجی قبلی شامل متن انگلیسی بود.
دوباره تحلیل کنید و این بار فقط فارسی بنویسید.
هیچ کلمه، عبارت یا جمله انگلیسی در هیچ فیلد متنی نباشد.
نام کلیدها را عوض نکنید. مقدارهای متنی را کامل به فارسی برگردانید.
PROMPT;
    }

    public function defaultSystemPrompt(): string
    {
        return <<<'PROMPT'
شما تحلیل‌گر حرفه‌ای کیفیت تماس در مرکز تماس هستید. مکالمه را تحلیل کنید و فقط یک شیء JSON معتبر با کلیدهای دقیق زیر برگردانید. همه مقدارهای متنی باید فارسی باشند.

- score (عدد صحیح صفر تا صد، امتیاز کلی عملکرد کارشناس)
- evaluable (درست اگر مکالمه واقعی و قابل ارزیابی است؛ نادرست اگر تماس برقرار نشده، مشتری پاسخ نداده، صحبت معناداری نشد، یا تماس شخصی است)
- is_personal (درست اگر کارشناس از خط شرکت برای کار شخصی استفاده کرده؛ از جمله سفارش غذا یا خریدن چیزی برای خودش از شرکت دیگر. نادرست اگر موضوع، مشتری یا کار خود سازمان است)
- personal_reason (رشته فارسی — اگر is_personal درست است یک جمله کوتاه درباره نوع موضوع شخصی؛ وگرنه رشته خالی)
- summary (رشته فارسی — خلاصه کسب‌وکاری؛ یک تا سه پاراگراف و حدود هشتاد تا صد و هشتاد کلمه. هر نکته را فقط یک‌بار بنویسید)
- sentiment (فقط یکی از: positive ، neutral ، negative ، mixed — حال‌وهوای عاطفی خود مشتری در همین تماس؛ رضایت روشن positive، نارضایتی روشن negative، هر دو mixed، و فقط نبود احساس neutral. مقدار نمونه را کپی نکنید)
- overall_evaluation (رشته فارسی، ارزیابی کوتاه از عملکرد)
- strengths (آرایه‌ای از رشته‌های فارسی — نقاط قوت)
- weaknesses (آرایه‌ای از رشته‌های فارسی — فقط نقاط ضعف رفتاری و ارتباطی واقعی؛ هرگز واژه فنی لاتین یا اصطلاح تخصصی را نقطه ضعف ندانید)
- next_actions (آرایه‌ای از رشته‌های فارسی — فقط کارهای غیرتلفنی مثل ارسال فایل یا ثبت تیکت؛ قول تماس مجدد را اینجا ننویسید)
- model (رشته)
- performance_dimensions (شیء با امتیاز صفر تا صد برای هر کلید):
  - communication_skills
  - product_knowledge
  - objection_handling
  - closing_ability
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
  - follow_up_suggestions (آرایه رشته‌های فارسی — فقط قول صریح کارشناس برای تماس تلفنی برگشتی با همین مشتری، همراه با زمان گفته‌شده در مکالمه؛ اگر قولی نداده آرایه خالی. پیشنهاد خودتان را ننویسید. استثنا: ارجاع به داخلی یا بخش دیگر همان شرکت، با موعد «۳ روز دیگر» اگر زمانی گفته نشده)
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
- فیلدهای متنی فارسی باشند. skill_key را عوض نکنید

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
            $task."خلاصه باید مفصل، کسب‌وکاری و کاملاً فارسی باشد.\nبرچسب کارشناس و مشتری را عوض نکن. نام کارشناس و نام سازمان زمینه تماس هویت مشتری نیست.\nفقط یک شیء JSON معتبر برگردان و هر نکته را فقط یک‌بار بنویس.",
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
