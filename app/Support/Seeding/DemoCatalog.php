<?php

namespace App\Support\Seeding;

use App\Models\Organization;

final class DemoCatalog
{
    public const EMAIL_DOMAIN = 'gmail.com';

    /** Plain-text password for demo users only — never use in production. */
    public const DEMO_PASSWORD = '123456789';

    /** ۲۰٬۰۰۰ تومان — stored as Rials (۱ تومان = ۱۰ ریال). */
    public const WALLET_BALANCE_IRR = 200_000;

    public const ORGANIZATION_COUNT = 20;

    public const EMPLOYEES_PER_ORGANIZATION = 4;

    public const CUSTOMERS_PER_ORGANIZATION = 48;

    public const CALLS_PER_ORGANIZATION = 150;

    public const CALLS_TODAY_PER_ORGANIZATION = 10;

    /** Calls are spread across this many recent days (including today). */
    public const DEMO_CALL_RECENT_DAYS = 30;

    /** @var list<string> */
    public const MALE_AVATARS = ['/img/avatar1.webp', '/img/avatar2.webp'];

    /** @var list<string> */
    public const FEMALE_AVATARS = ['/img/avatar3.webp', '/img/avatar4.webp'];

    public static function email(string $emailFirst, string $emailLast, ?string $suffix = null): string
    {
        $local = self::emailLocalPart($emailFirst, $emailLast, $suffix);

        return "{$local}@".self::EMAIL_DOMAIN;
    }

    public static function emailLocalPart(string $emailFirst, string $emailLast, ?string $suffix = null): string
    {
        $local = strtolower("{$emailFirst}.{$emailLast}");

        if (filled($suffix)) {
            $local .= '.'.strtolower($suffix);
        }

        return preg_replace('/[^a-z0-9.]/', '', $local) ?? $local;
    }

    public static function employerEmail(int $index): string
    {
        $profile = self::employerProfiles()[$index] ?? self::employerProfiles()[0];

        return self::email($profile['email_first'], $profile['email_last']);
    }

    public static function employeeEmail(string $emailFirst, string $emailLast, int $variant = 1): string
    {
        return self::email($emailFirst, $emailLast, $variant > 1 ? (string) $variant : null);
    }

    public static function isDemoUserEmail(string $email): bool
    {
        return str_ends_with(strtolower($email), '@'.self::EMAIL_DOMAIN);
    }

    public static function credentialsSummary(): string
    {
        return sprintf(
            'Demo login — employer: firstname.lastname@%s · employee: firstname.lastname@%s · password: %s',
            self::EMAIL_DOMAIN,
            self::EMAIL_DOMAIN,
            self::DEMO_PASSWORD,
        );
    }

    public static function exampleEmployerLogin(): string
    {
        return self::employerEmail(0);
    }

    public static function resolveOrganizationSlug(Organization $organization): string
    {
        foreach (self::organizations() as $definition) {
            if ($definition['title'] === $organization->title) {
                return $definition['slug'];
            }
        }

        return 'org'.$organization->id;
    }

    public static function organizationIndex(Organization $organization): ?int
    {
        foreach (self::organizations() as $index => $definition) {
            if ($definition['title'] === $organization->title) {
                return $index;
            }
        }

        return null;
    }

    /** @return list<array{slug: string, title: string, industry: string}> */
    public static function organizations(): array
    {
        return [
            ['slug' => 'parsin', 'title' => 'مرکز تماس پارسین', 'industry' => 'فروش و پشتیبانی'],
            ['slug' => 'novintamas', 'title' => 'نوین‌تماس تهران', 'industry' => 'خدمات مشتریان'],
            ['slug' => 'irankal', 'title' => 'ایران‌کال ارتباطات', 'industry' => 'تجارت الکترونیک'],
            ['slug' => 'hamrahplus', 'title' => 'همراه‌پلاس پشتیبانی', 'industry' => 'مخابرات'],
            ['slug' => 'samanray', 'title' => 'سامان‌رای تماس', 'industry' => 'فناوری مالی'],
            ['slug' => 'padidatel', 'title' => 'پدیده‌تل اصفهان', 'industry' => 'آموزش آنلاین'],
            ['slug' => 'barmancc', 'title' => 'بارمان ارتباط', 'industry' => 'حمل‌ونقل'],
            ['slug' => 'rahyab', 'title' => 'رهیاب مشاوره', 'industry' => 'مشاوره مالی'],
            ['slug' => 'atlassupport', 'title' => 'اطلس پشتیبانی', 'industry' => 'گردشگری'],
            ['slug' => 'mehrvocal', 'title' => 'مهروکالا تماس', 'industry' => 'خرده‌فروشی'],
            ['slug' => 'kavoshnet', 'title' => 'کاوش‌نت ارتباط', 'industry' => 'نرم‌افزار سازمانی'],
            ['slug' => 'ariahelp', 'title' => 'آریا هلپ‌دسک', 'industry' => 'خدمات پس از فروش'],
            ['slug' => 'tadbirline', 'title' => 'تدبیرلاین تماس', 'industry' => 'بیمه'],
            ['slug' => 'niktel', 'title' => 'نیک‌تل خدمات', 'industry' => 'سلامت دیجیتال'],
            ['slug' => 'zarrinvoip', 'title' => 'زرین‌ویپ پشتیبانی', 'industry' => 'لوازم خانگی'],
            ['slug' => 'rayantamas', 'title' => 'رایان‌تماس اهواز', 'industry' => 'خدمات مشتریان'],
            ['slug' => 'pardiscall', 'title' => 'پردیس‌کال ارتباطات', 'industry' => 'فروش B2B'],
            ['slug' => 'omidvoip', 'title' => 'امیدویپ جنوب', 'industry' => 'مخابرات'],
            ['slug' => 'tosienet', 'title' => 'توسعه‌نت خدمات', 'industry' => 'نرم‌افزار سازمانی'],
            ['slug' => 'sepehrsupport', 'title' => 'سپهرپشتیبانی', 'industry' => 'خدمات پس از فروش'],
        ];
    }

    /** @return list<array{name: string, email_first: string, email_last: string, gender: string}> */
    public static function employerProfiles(): array
    {
        return [
            ['name' => 'محسن رضایی', 'email_first' => 'mohsen', 'email_last' => 'rezaei', 'gender' => 'male'],
            ['name' => 'الهام فرهادی', 'email_first' => 'elham', 'email_last' => 'farahani', 'gender' => 'female'],
            ['name' => 'سعید مرادی', 'email_first' => 'saeed', 'email_last' => 'moradi', 'gender' => 'male'],
            ['name' => 'نیلوفر باقری', 'email_first' => 'niloufar', 'email_last' => 'bagheri', 'gender' => 'female'],
            ['name' => 'حمید کاویانی', 'email_first' => 'hamid', 'email_last' => 'kaviany', 'gender' => 'male'],
            ['name' => 'پریسا سلطانی', 'email_first' => 'parisa', 'email_last' => 'soltani', 'gender' => 'female'],
            ['name' => 'جواد نیک‌نام', 'email_first' => 'javad', 'email_last' => 'niknam', 'gender' => 'male'],
            ['name' => 'مینا حاتمی', 'email_first' => 'mina', 'email_last' => 'hatami', 'gender' => 'female'],
            ['name' => 'بهرام قنبری', 'email_first' => 'bahram', 'email_last' => 'ghanbari', 'gender' => 'male'],
            ['name' => 'شیدا یزدانی', 'email_first' => 'shida', 'email_last' => 'yazdani', 'gender' => 'female'],
            ['name' => 'فرهاد ملکی', 'email_first' => 'farhad', 'email_last' => 'maleki', 'gender' => 'male'],
            ['name' => 'گلناز عباسی', 'email_first' => 'golnaz', 'email_last' => 'abbasi', 'gender' => 'female'],
            ['name' => 'داریوش شفیعی', 'email_first' => 'dariush', 'email_last' => 'shafiei', 'gender' => 'male'],
            ['name' => 'سمیرا طاهری', 'email_first' => 'samira', 'email_last' => 'taheri', 'gender' => 'female'],
            ['name' => 'یاسر امینی', 'email_first' => 'yaser', 'email_last' => 'amini', 'gender' => 'male'],
            ['name' => 'رضا پورمحمدی', 'email_first' => 'reza', 'email_last' => 'pourmohammadi', 'gender' => 'male'],
            ['name' => 'حدیث محمدزاده', 'email_first' => 'hadis', 'email_last' => 'mohammadzadeh', 'gender' => 'female'],
            ['name' => 'امیرحسین عبدی', 'email_first' => 'amirhossein', 'email_last' => 'abdi', 'gender' => 'male'],
            ['name' => 'رویا کشاورز', 'email_first' => 'roya', 'email_last' => 'keshavarz', 'gender' => 'female'],
            ['name' => 'وحید جلالی', 'email_first' => 'vahid', 'email_last' => 'jalali', 'gender' => 'male'],
        ];
    }

    public static function employerName(int $index): string
    {
        return self::employerProfiles()[$index]['name'] ?? 'مدیر شرکت';
    }

    /** @return list<array{first_name: string, last_name: string, email_first: string, email_last: string, gender: string, department: string, position: string}> */
    public static function employeeProfiles(): array
    {
        return [
            ['first_name' => 'علی', 'last_name' => 'محمدی', 'email_first' => 'ali', 'email_last' => 'mohammadi', 'gender' => 'male', 'department' => 'فروش', 'position' => 'کارشناس ارشد'],
            ['first_name' => 'زهرا', 'last_name' => 'کریمی', 'email_first' => 'zahra', 'email_last' => 'karimi', 'gender' => 'female', 'department' => 'پشتیبانی', 'position' => 'کارشناس'],
            ['first_name' => 'رضا', 'last_name' => 'حسینی', 'email_first' => 'reza', 'email_last' => 'hosseini', 'gender' => 'male', 'department' => 'واحد VIP', 'position' => 'سرپرست تیم'],
            ['first_name' => 'مریم', 'last_name' => 'جعفری', 'email_first' => 'maryam', 'email_last' => 'jafari', 'gender' => 'female', 'department' => 'بازاریابی تلفنی', 'position' => 'کارشناس'],
            ['first_name' => 'امیر', 'last_name' => 'نوری', 'email_first' => 'amir', 'email_last' => 'nouri', 'gender' => 'male', 'department' => 'فروش', 'position' => 'کارشناس'],
            ['first_name' => 'سارا', 'last_name' => 'احمدی', 'email_first' => 'sara', 'email_last' => 'ahmadi', 'gender' => 'female', 'department' => 'پشتیبانی', 'position' => 'کارشناس ارشد'],
            ['first_name' => 'حسین', 'last_name' => 'رحیمی', 'email_first' => 'hossein', 'email_last' => 'rahimi', 'gender' => 'male', 'department' => 'فروش', 'position' => 'کارشناس'],
            ['first_name' => 'فاطمه', 'last_name' => 'موسوی', 'email_first' => 'fateme', 'email_last' => 'mousavi', 'gender' => 'female', 'department' => 'پشتیبانی', 'position' => 'کارشناس'],
            ['first_name' => 'مهدی', 'last_name' => 'قاسمی', 'email_first' => 'mehdi', 'email_last' => 'ghasemi', 'gender' => 'male', 'department' => 'واحد VIP', 'position' => 'کارشناس ارشد'],
            ['first_name' => 'نرگس', 'last_name' => 'صادقی', 'email_first' => 'narges', 'email_last' => 'sadeghi', 'gender' => 'female', 'department' => 'بازاریابی تلفنی', 'position' => 'کارشناس'],
            ['first_name' => 'پویا', 'last_name' => 'اکبری', 'email_first' => 'pouya', 'email_last' => 'akbari', 'gender' => 'male', 'department' => 'فروش', 'position' => 'کارشناس'],
            ['first_name' => 'لیلا', 'last_name' => 'زارع', 'email_first' => 'leila', 'email_last' => 'zare', 'gender' => 'female', 'department' => 'پشتیبانی', 'position' => 'کارشناس'],
            ['first_name' => 'کامران', 'last_name' => 'بهرامی', 'email_first' => 'kamran', 'email_last' => 'bahrami', 'gender' => 'male', 'department' => 'فروش', 'position' => 'سرپرست تیم'],
            ['first_name' => 'شیما', 'last_name' => 'توکلی', 'email_first' => 'shima', 'email_last' => 'tavakoli', 'gender' => 'female', 'department' => 'پشتیبانی', 'position' => 'کارشناس'],
            ['first_name' => 'آرمان', 'last_name' => 'شریفی', 'email_first' => 'arman', 'email_last' => 'sharifi', 'gender' => 'male', 'department' => 'بازاریابی تلفنی', 'position' => 'کارشناس ارشد'],
        ];
    }

    public static function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? $phone;
    }

    public static function formatMobile(int $orgIndex, int $customerIndex): string
    {
        $suffix = str_pad((string) (($orgIndex * 100) + $customerIndex), 7, '0', STR_PAD_LEFT);

        return '0912'.substr($suffix, 0, 7);
    }

    /** @return list<string> */
    public static function summaries(): array
    {
        return [
            'مشتری درباره زمان تحویل سفارش سوال کرد و کارشناس با ارائه وضعیت مرسوله و زمان تقریبی، رضایت اولیه ایجاد کرد.',
            'تماس فروش برای معرفی بسته جدید بود؛ مشتری به قیمت حساس بود اما در پایان درخواست پیش‌فاکتور داد.',
            'مشتری از کیفیت پشتیبانی قبلی ناراضی بود؛ کارشناس با عذرخواهی و ارائه راه‌حل فوری، تنش را کاهش داد.',
            'درخواست فنی مربوط به فعال‌سازی سرویس بود و کارشناس مراحل را گام‌به‌گام توضیح داد.',
            'مشتری برای تمدید قرارداد تماس گرفت؛ مذاکره روی تخفیف انجام شد و پیگیری فردا هماهنگ شد.',
            'تماس ورودی درباره مغایرت فاکتور بود؛ پس از بررسی، اصلاحیه برای واحد مالی ثبت شد.',
            'مشتری به دنبال ارتقای پلن بود؛ کارشناس مزایا را مقایسه کرد و دمو آنلاین تنظیم نمود.',
            'پیگیری سرنخ فروش از کمپین هفته قبل؛ مشتری هنوز نیاز به تأیید مدیریت داشت.',
        ];
    }

    /** @return list<string> */
    public static function strengths(): array
    {
        return ['گوش دادن فعال', 'توضیح شفاف', 'مدیریت اعتراض', 'پیگیری دقیق', 'لحن حرفه‌ای'];
    }

    /** @return list<string> */
    public static function weaknesses(): array
    {
        return ['فرصت فروش مکمل از دست رفت', 'جمع‌بندی ضعیف انتهای تماس', 'عدم تأیید نهایی نیاز مشتری'];
    }

    /** @return list<string> */
    public static function nextActions(): array
    {
        return ['ارسال پیش‌فاکتور', 'تماس پیگیری فردا', 'ثبت تیکت فنی', 'هماهنگی دمو محصول'];
    }

    /** @return list<string> */
    public static function personNames(): array
    {
        return [
            'علی رضایی', 'مریم احمدی', 'حسین کریمی', 'زهرا موسوی', 'رضا نوری',
            'فاطمه حسینی', 'مهدی جعفری', 'سارا محمدی', 'امیر صادقی', 'نرگس قاسمی',
            'پویا اکبری', 'لیلا فرهادی', 'کامران بهرامی', 'شیما توکلی', 'بهروز یوسفی',
            'ندا شریفی', 'سعید کاظمی', 'مینا رستمی', 'حامد نجفی', 'پریسا مرادی',
            'کیانوش عباسی', 'هانیه سلیمی', 'فرزاد محمدپور', 'آرزو حیدری', 'بابک اسماعیلی',
            'گلاره ناصری', 'شهاب کریمی', 'یاسمن علوی', 'نیما فتحی', 'سمانه رضوی',
            'اردشیر زمانی', 'مهسا طاهری', 'پیمان کاویانی', 'الناز رحمانیان', 'سیاوش امیری',
            'حدیثه باقری', 'رامین شفیعی', 'نسترن جلالی', 'کیوان احمدیان', 'فروغ صالحی',
            'مازیار خسروی', 'شقایق نعمتی', 'بهنام پناهی', 'مرجان داوودی', 'آرش کیانی',
            'سحر محمودی', 'داریوش تقوی', 'پگاه ابراهیمی',
        ];
    }

    /** @return list<string> */
    public static function customerNames(): array
    {
        return [
            'شرکت آفتاب', 'فروشگاه رایان', 'کلینیک سلامت', 'آژانس سفر نور', 'گروه صنعتی البرز',
            'استودیو طراحی ماه', 'رستوران سنتی باغ', 'مجتمع مسکونی پارس', 'مؤسسه آموزشی دانا',
            'کارگاه تولیدی آریا', 'داروخانه مهر', 'دفتر حقوقی عدل', 'باشگاه ورزشی پیکر',
            'شرکت حمل‌ونقل سپهر', 'فروشگاه زنجیره‌ای نسیم', 'هلدینگ فناوری ویرا',
        ];
    }

    public static function callOutcomeLabel(string $outcome): string
    {
        return match ($outcome) {
            'success' => 'رضایت نسبی داشت',
            'follow_up' => 'منتظر پیگیری بعدی بود',
            'escalated' => 'درخواست پیگیری مدیریتی داشت',
            'failed' => 'ناراضی یا پاسخگو نبود',
            default => 'وضعیت نامشخص',
        };
    }

    /** @return list<array<string, mixed>> */
    public static function callScenarios(): array
    {
        return [
            [
                'key' => 'delivery_status',
                'category' => 'پشتیبانی',
                'title' => 'پیگیری وضعیت ارسال سفارش',
                'outcome' => 'success',
                'lead_bias' => -5,
                'intent' => 'پیگیری زمان تحویل و وضعیت مرسوله',
                'concern_type' => 'timing',
                'concern_text' => 'نگرانی از تأخیر احتمالی در تحویل سفارش',
                'lead_reason' => 'تماس پشتیبانی با تمایل خرید مجدد پایین اما ریسک ریزش متوسط',
                'buying_signals' => ['سوال درباره زمان تحویل سفارش بعدی'],
                'keywords' => ['ارسال', 'تحویل', 'کد رهگیری'],
                'notes' => 'مشتری کد سفارش را تأیید کرد و وضعیت ارسال توضیح داده شد.',
                'summary_opening' => 'موضوع اصلی، پیگیری وضعیت ارسال و زمان تحویل بود.',
                'summary_middle' => 'کارشناس با استعلام از سیستم، آخرین وضعیت مرسوله و بازه تحویل را اعلام کرد.',
                'summary_close' => 'مشتری از شفاف‌سازی به‌عمل‌آمده تشکر کرد و درخواست پیامک وضعیت داشت.',
                'next_actions' => ['ارسال پیامک وضعیت مرسوله', 'پیگیری تحویل در صورت تأخیر'],
                'dialogue' => [
                    ['speaker' => 'customer', 'text' => 'سفارشم هفته پیش ثبت شده ولی هنوز به دستم نرسیده. می‌خواستم بدونم دقیقاً کجاست.'],
                    ['speaker' => 'agent', 'text' => 'حتماً بررسی می‌کنم. لطفاً شماره سفارش یا کد رهگیری را بفرمایید.'],
                    ['speaker' => 'customer', 'text' => 'کد رهگیری ۴۵۸۹۲۱ است. برای جلسه فردا نیاز دارم حتماً برسد.'],
                    ['speaker' => 'agent', 'text' => 'مرسوله از مرکز توزیع خارج شده و طبق سیستم، فردا قبل از ظهر به دست شما می‌رسد. پیامک وضعیت هم ارسال می‌کنم.'],
                ],
            ],
            [
                'key' => 'sales_package',
                'category' => 'فروش',
                'title' => 'معرفی بسته خدماتی جدید',
                'outcome' => 'follow_up',
                'lead_bias' => 12,
                'intent' => 'دریافت پیش‌فاکتور و مقایسه بسته‌ها',
                'concern_type' => 'price',
                'concern_text' => 'حساسیت به قیمت و درخواست تخفیف سازمانی',
                'lead_reason' => 'سیگنال خرید بالا با نیاز به تأیید مالی',
                'buying_signals' => ['درخواست پیش‌فاکتور', 'پرسش درباره پرداخت اقساطی'],
                'keywords' => ['پیش‌فاکتور', 'بسته حرفه‌ای', 'تخفیف'],
                'notes' => 'مشتری درخواست ارسال پیش‌فاکتور تا شنبه داشت.',
                'summary_opening' => 'تماس فروش برای معرفی بسته جدید و بررسی نیازهای فعلی مشتری بود.',
                'summary_middle' => 'کارشناس مزایا و تفاوت پلن‌ها را مقایسه کرد و هزینه ماهانه را شفاف اعلام نمود.',
                'summary_close' => 'مشتری درخواست پیش‌فاکتور رسمی داد و گفت برای تأیید مدیریت مالی تا شنبه تصمیم می‌گیرد.',
                'next_actions' => ['ارسال پیش‌فاکتور', 'تماس پیگیری شنبه ساعت ۱۰'],
                'dialogue' => [
                    ['speaker' => 'customer', 'text' => 'پیامک معرفی بسته جدیدتون رو دیدم. می‌خوام بدونم برای تیم پنج نفره ما چه هزینه‌ای داره.'],
                    ['speaker' => 'agent', 'text' => 'پلن حرفه‌ای برای تیم شما مناسب‌تر است. امکانات گزارش‌گیری و تحلیل تماس را هم شامل می‌شود.'],
                    ['speaker' => 'customer', 'text' => 'قیمت مهمه. اگر تخفیف سازمانی دارید، می‌تونیم سریع‌تر جلو بریم.'],
                    ['speaker' => 'agent', 'text' => 'برای قرارداد سالانه، ده درصد تخفیف داریم. پیش‌فاکتور را همین امروز ایمیل می‌کنم.'],
                ],
            ],
            [
                'key' => 'support_complaint',
                'category' => 'پشتیبانی',
                'title' => 'رسیدگی به نارضایتی از پشتیبانی قبلی',
                'outcome' => 'escalated',
                'lead_bias' => -15,
                'intent' => 'اعتراض به کیفیت پاسخگویی قبلی و درخواست راه‌حل فوری',
                'concern_type' => 'trust',
                'concern_text' => 'کاهش اعتماد به پشتیبانی پس از تجربه نامطلوب',
                'needs_attention' => true,
                'attention_categories' => ['service'],
                'attention_reason' => 'مشتری به کیفیت پشتیبانی و تأخیر در رسیدگی اعتراض دارد.',
                'lead_reason' => 'ریسک ریزش مشتری در صورت عدم پیگیری سریع',
                'buying_signals' => [],
                'keywords' => ['شکایت', 'تیکت باز', 'پیگیری فوری'],
                'notes' => 'تیکت فوری برای واحد فنی ثبت شد.',
                'summary_opening' => 'مشتری از تأخیر در رسیدگی به تیکت قبلی ناراضی بود.',
                'summary_middle' => 'کارشناس با عذرخواهی رسمی، وضعیت تیکت را بررسی و راه‌حل موقت ارائه داد.',
                'summary_close' => 'برای اطمینان مشتری، پیگیری ویژه تا پایان امروز هماهنگ شد.',
                'next_actions' => ['ثبت تیکت فوری', 'تماس بازخورد امروز ساعت ۱۷'],
                'dialogue' => [
                    ['speaker' => 'customer', 'text' => 'سه روزه تیکت زدم و هنوز کسی جواب درستی نداده. این وضعیت قابل قبول نیست.'],
                    ['speaker' => 'agent', 'text' => 'حق با شماست و عذرخواهی می‌کنم. اجازه بدید همین الان وضعیت پرونده را با اولویت بالا بررسی کنم.'],
                    ['speaker' => 'customer', 'text' => 'سیستم ما متوقف شده و هر ساعت تأخیر برای ما هزینه دارد.'],
                    ['speaker' => 'agent', 'text' => 'راه‌حل موقت را الان اعمال می‌کنم و همکار فنی ظرف یک ساعت با شما تماس می‌گیرد.'],
                ],
            ],
            [
                'key' => 'technical_activation',
                'category' => 'فنی',
                'title' => 'فعال‌سازی سرویس و راه‌اندازی اولیه',
                'outcome' => 'success',
                'lead_bias' => 5,
                'intent' => 'راهنمایی گام‌به‌گام برای فعال‌سازی سرویس',
                'concern_type' => 'technical',
                'concern_text' => 'ابهام در مراحل فنی راه‌اندازی',
                'lead_reason' => 'تماس فنی با احتمال تمدید در صورت راه‌اندازی موفق',
                'buying_signals' => ['درخواست آموزش استفاده از پنل'],
                'keywords' => ['فعال‌سازی', 'نام کاربری', 'راهنما'],
                'notes' => 'مشتری توانست وارد پنل شود.',
                'summary_opening' => 'مشتری برای فعال‌سازی سرویس جدید تماس گرفته بود.',
                'summary_middle' => 'کارشناس مراحل ورود، تنظیم رمز و تست اولیه را به‌صورت گام‌به‌گام توضیح داد.',
                'summary_close' => 'مشتری تأیید کرد که وارد پنل شده و آماده استفاده است.',
                'next_actions' => ['ارسال ویدیوی آموزشی کوتاه', 'تماس چک‌لیست فردا'],
                'dialogue' => [
                    ['speaker' => 'customer', 'text' => 'قرارداد را امضا کردیم ولی نمی‌دونم از کجا باید سرویس را فعال کنم.'],
                    ['speaker' => 'agent', 'text' => 'اول لینک فعال‌سازی را برایتان پیامک می‌کنم. سپس نام کاربری و رمز موقت را تنظیم می‌کنیم.'],
                    ['speaker' => 'customer', 'text' => 'الان وارد شدم ولی صفحه داشبورد خالی است. طبیعی است؟'],
                    ['speaker' => 'agent', 'text' => 'بله، تا اتصال اولین تماس طبیعی است. الان یک تماس تست ثبت می‌کنم تا ببینید.'],
                ],
            ],
            [
                'key' => 'contract_renewal',
                'category' => 'فروش',
                'title' => 'تمدید قرارداد سالانه',
                'outcome' => 'follow_up',
                'lead_bias' => 18,
                'intent' => 'تمدید قرارداد با شرایط بهتر',
                'concern_type' => 'price',
                'concern_text' => 'درخواست تخفیف تمدید نسبت به سال قبل',
                'lead_reason' => 'احتمال تمدید بالا با مذاکره روی قیمت',
                'buying_signals' => ['پرسش درباره قرارداد دو ساله', 'درخواست قفل قیمت'],
                'keywords' => ['تمدید', 'قرارداد', 'تخفیف وفاداری'],
                'notes' => 'پیشنهاد تخفیف پنج درصدی برای قرارداد دو ساله ارائه شد.',
                'summary_opening' => 'موضوع تماس، تمدید قرارداد و بررسی شرایط جدید بود.',
                'summary_middle' => 'کارشناس عملکرد یک سال گذشته را مرور کرد و بسته تمدید را پیشنهاد داد.',
                'summary_close' => 'مشتری درخواست مهلت تا فردا برای تأیید مدیریت داشت.',
                'next_actions' => ['ارسال پیشنهاد رسمی تمدید', 'تماس پیگیری فردا'],
                'dialogue' => [
                    ['speaker' => 'customer', 'text' => 'قرارداد ما ماه آینده تمام می‌شود. می‌خوایم تمدید کنیم ولی هزینه باید منطقی باشد.'],
                    ['speaker' => 'agent', 'text' => 'با توجه به سابقه همکاری، پلن فعلی با قیمت به‌روز و یک ماه پشتیبانی اضافه پیشنهاد می‌کنم.'],
                    ['speaker' => 'customer', 'text' => 'اگر قرارداد دو ساله ببندیم، تخفیف بیشتری می‌گیریم؟'],
                    ['speaker' => 'agent', 'text' => 'بله، برای قرارداد ۲۴ ماهه پنج درصد تخفیف وفاداری داریم. متن قرارداد را امروز ارسال می‌کنم.'],
                ],
            ],
            [
                'key' => 'billing_dispute',
                'category' => 'مالی',
                'title' => 'بررسی مغایرت فاکتور',
                'outcome' => 'success',
                'lead_bias' => -8,
                'intent' => 'اصلاح فاکتور و رفع مغایرت مالی',
                'concern_type' => 'price',
                'concern_text' => 'مغایرت مبلغ فاکتور با توافق قبلی',
                'lead_reason' => 'تماس مالی با ریسک نارضایتی در صورت تأخیر اصلاح',
                'buying_signals' => [],
                'keywords' => ['فاکتور', 'مغایرت', 'اصلاحیه'],
                'notes' => 'اصلاحیه برای واحد مالی ثبت شد.',
                'summary_opening' => 'مشتری درباره اختلاف مبلغ فاکتور آخر سوال کرد.',
                'summary_middle' => 'کارشناس ردیف‌های فاکتور را با قرارداد تطبیق داد و خطای محاسبه را تأیید کرد.',
                'summary_close' => 'اصلاحیه ظرف ۲۴ ساعت کاری ارسال می‌شود.',
                'next_actions' => ['ثبت اصلاحیه فاکتور', 'اطلاع‌رسانی به واحد مالی'],
                'dialogue' => [
                    ['speaker' => 'customer', 'text' => 'فاکتور این ماه هفتاد تومان بیشتر از توافق ماست. لطفاً بررسی کنید.'],
                    ['speaker' => 'agent', 'text' => 'شماره فاکتور را دارم. یک دقیقه اجازه بدید ردیف خدمات اضافه را چک کنم.'],
                    ['speaker' => 'customer', 'text' => 'ما آن بسته اضافه را فعال نکرده‌ایم.'],
                    ['speaker' => 'agent', 'text' => 'درست می‌فرمایید. خطای سیستمی بوده و اصلاحیه صادر می‌کنیم.'],
                ],
            ],
            [
                'key' => 'plan_upgrade',
                'category' => 'فروش',
                'title' => 'ارتقای پلن و دمو محصول',
                'outcome' => 'success',
                'lead_bias' => 20,
                'intent' => 'ارتقای پلن و مشاهده دمو امکانات جدید',
                'concern_type' => 'technical',
                'concern_text' => 'نیاز به اطمینان از سازگاری با زیرساخت فعلی',
                'lead_reason' => 'تمایل به ارتقا با نیاز به دمو آنلاین',
                'buying_signals' => ['درخواست دمو', 'پرسش زمان استقرار'],
                'keywords' => ['ارتقا', 'دمو', 'پلن سازمانی'],
                'notes' => 'جلسه دمو برای سه‌شنبه ساعت ۱۱ ثبت شد.',
                'summary_opening' => 'مشتری برای ارتقای پلن و استفاده از گزارش‌های پیشرفته تماس گرفت.',
                'summary_middle' => 'کارشناس تفاوت پلن‌ها را توضیح داد و زمان استقرار را شفاف اعلام کرد.',
                'summary_close' => 'دمو آنلاین برای سه‌شنبه هماهنگ شد.',
                'next_actions' => ['هماهنگی دمو آنلاین', 'ارسال مقایسه پلن‌ها'],
                'dialogue' => [
                    ['speaker' => 'customer', 'text' => 'تیم ما بزرگ‌تر شده و به گزارش تحلیلی نیاز داریم. چه پلنی مناسب‌تره؟'],
                    ['speaker' => 'agent', 'text' => 'پلن سازمانی با داشبورد مدیریتی و خروجی اکسل پیشنهاد می‌شود. می‌توانم دمو زنده بگذارم.'],
                    ['speaker' => 'customer', 'text' => 'مهم اینه که با سیستم فعلی ما تداخل نداشته باشد.'],
                    ['speaker' => 'agent', 'text' => 'یکپارچگی از طریق همان پنل فعلی است. دمو را سه‌شنبه ساعت ۱۱ تنظیم می‌کنم.'],
                ],
            ],
            [
                'key' => 'sales_follow_up',
                'category' => 'فروش',
                'title' => 'پیگیری سرنخ کمپین',
                'outcome' => 'follow_up',
                'lead_bias' => 8,
                'intent' => 'پیگیری درخواست قبلی و حذف ابهامات',
                'concern_type' => 'timing',
                'concern_text' => 'نیاز به زمان برای تأیید مدیریت',
                'lead_reason' => 'سرنخ گرم با تصمیم‌گیری طی چند روز آینده',
                'buying_signals' => ['درخواست ارسال مستندات', 'پرسش زمان قرارداد'],
                'keywords' => ['کمپین', 'پیگیری', 'مدیریت'],
                'notes' => 'مستندات فنی برای مدیر عامل ارسال شد.',
                'summary_opening' => 'تماس پیگیری سرنخ ناشی از کمپین هفته گذشته بود.',
                'summary_middle' => 'کارشناس سوالات باقی‌مانده را پاسخ داد و مستندات را توضیح داد.',
                'summary_close' => 'مشتری گفت تا پایان هفته با تأیید مدیریت برمی‌گردد.',
                'next_actions' => ['ارسال مستندات فنی', 'تماس پیگیری پنج‌شنبه'],
                'dialogue' => [
                    ['speaker' => 'agent', 'text' => 'هفته پیش درخواست دمو ثبت کرده بودید. می‌خواستم ببینم سوالی مانده یا نه.'],
                    ['speaker' => 'customer', 'text' => 'مدیر عامل باید تأیید کند. مستندات فنی را برایشان بفرستید.'],
                    ['speaker' => 'agent', 'text' => 'حتماً. خلاصه مدیریتی و راهنمای استقرار را ایمیل می‌کنم.'],
                    ['speaker' => 'customer', 'text' => 'خوبه. احتمالاً تا پنج‌شنبه جواب می‌دیم.'],
                ],
            ],
        ];
    }
}
