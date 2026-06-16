/** @typedef {{ selector?: string, title: string, content: string, placement?: string, align?: string, route?: string, center?: boolean, fab?: boolean }} TourStep */

/** @type {Record<string, { label: string, steps: TourStep[] }>} */
export const employerPageTours = {
    'employer.dashboard': {
        label: 'داشبورد',
        steps: [
            {
                selector: '[data-tour="dashboard-hero"]',
                title: 'نمای کلی سازمان',
                content: 'خلاصه عملکرد تیم، میانگین امتیاز کیفیت و آمار کلیدی ۳۰ روز اخیر را اینجا می‌بینید.',
            },
            {
                selector: '[data-tour="dashboard-stats"]',
                title: 'شاخص‌های سریع',
                content: 'تماس‌های امروز، کیفیت لید و رضایت مشتری — برای پایش روزانه مفید است.',
            },
            {
                selector: '[data-tour="dashboard-agents"]',
                title: 'عملکرد کارشناسان',
                content: 'کارت هر کارشناس را ببینید، فیلتر برترین‌ها یا نیازمند توجه را بزنید و برای جزئیات روی کارت کلیک کنید.',
            },
            {
                selector: '[data-tour="dashboard-quality"]',
                title: 'روند کیفیت تیم',
                content: 'نمودار روند میانگین امتیاز مکالمه در بازه اخیر.',
            },
            {
                selector: '[data-tour="dashboard-activity"]',
                title: 'فعالیت اخیر',
                content: 'آخرین رویدادهای مهم سازمان مثل تحلیل تماس یا تغییرات تیم.',
            },
        ],
    },
    'employer.intelligence.performance': {
        label: 'عملکرد کارشناسان',
        steps: [
            {
                selector: '[data-tour="page-header"]',
                title: 'عملکرد کارشناسان',
                content: 'رتبه‌بندی، مقایسه و شناسایی فرصت‌های مربیگری تیم تماس.',
            },
            {
                selector: '[data-tour="performance-filters"]',
                title: 'فیلتر بازه زمانی',
                content: 'بازه زمانی را انتخاب کنید یا پروفایل هر کارشناس را از چیپ‌ها باز کنید. خروجی CSV/Excel/PDF هم از همین بالا در دسترس است.',
            },
            {
                selector: '[data-tour="performance-summary"]',
                title: 'جمع‌بندی مدیریتی',
                content: 'خلاصه متنی وضعیت تیم بر اساس داده‌های فیلترشده.',
            },
            {
                selector: '[data-tour="performance-cards"]',
                title: 'کارت‌های عملکرد',
                content: 'فیلتر برترین‌ها و نیازمند توجه. کلیک روی هر کارت به صفحه جزئیات همان کارشناس می‌برد.',
            },
            {
                selector: '[data-tour="performance-charts"]',
                title: 'نمودارهای تیم',
                content: 'روند کیفیت و مقایسه امتیاز کارشناسان در یک نما.',
            },
            {
                selector: '[data-tour="performance-rankings"]',
                title: 'رتبه‌بندی‌ها',
                content: 'برترین‌ها در امتیاز، لید، پیشرفت و حجم تماس.',
            },
        ],
    },
    'employer.intelligence.index': {
        label: 'تحلیل تماس‌ها',
        steps: [
            {
                selector: '[data-tour="page-header"]',
                title: 'تحلیل تماس‌ها',
                content: 'پایش کیفیت مکالمات، فیلتر پیشرفته و ورود به جزئیات هر تحلیل.',
            },
            {
                selector: '[data-tour="analysis-filters"]',
                title: 'فیلترها',
                content: 'بازه زمانی، کارشناس، وضعیت تماس، جهت و مدت. برای بازه دلخواه تاریخ‌ها را انتخاب و «تایید بازه» را بزنید.',
            },
            {
                selector: '[data-tour="analysis-stats"]',
                title: 'شاخص‌های فیلترشده',
                content: 'تعداد تحلیل، میانگین امتیاز، لید، رضایت و آمار تماس در بازه فعلی.',
            },
            {
                selector: '[data-tour="analysis-charts"]',
                title: 'نمودارهای تحلیل',
                content: 'روند کیفیت، حجم تحلیل، توزیع لید، احساسات و نگرانی‌های پرتکرار.',
            },
            {
                selector: '[data-tour="analysis-list"]',
                title: 'لیست تحلیل‌ها',
                content: 'هر ردیف قابل کلیک است. جستجو، مرتب‌سازی ستون‌ها و فیلتر سریع از همین بخش.',
            },
        ],
    },
    'employer.employees.index': {
        label: 'کارشناسان',
        steps: [
            {
                selector: '[data-tour="page-header"]',
                title: 'مدیریت کارشناسان',
                content: 'افزودن عضو تیم، ویرایش پروفایل و کنترل وضعیت فعال/غیرفعال.',
            },
            {
                selector: '[data-tour="employees-search"]',
                title: 'جستجو',
                content: 'جستجوی سریع بر اساس نام یا اطلاعات کارشناس.',
            },
            {
                selector: '[data-tour="employees-table"]',
                title: 'جدول کارشناسان',
                content: 'ایمیل، بخش، تعداد تحلیل‌ها و وضعیت. از ستون عملیات می‌توانید ویرایش کنید.',
            },
        ],
    },
    'employer.employees.create': {
        label: 'افزودن کارشناس',
        steps: [
            {
                selector: '[data-tour="employee-form"]',
                title: 'فرم کارشناس',
                content: 'نام، ایمیل، بخش و سمت را وارد کنید. تصویر پروفایل اختیاری است. پس از ذخیره، کارشناس به فضای کار دسترسی پیدا می‌کند.',
            },
        ],
    },
    'employer.employees.edit': {
        label: 'ویرایش کارشناس',
        steps: [
            {
                selector: '[data-tour="employee-form"]',
                title: 'ویرایش اطلاعات',
                content: 'اطلاعات کارشناس، آواتار و وضعیت فعال بودن را به‌روزرسانی کنید.',
            },
        ],
    },
    'employer.customers.index': {
        label: 'مشتریان',
        steps: [
            {
                selector: '[data-tour="page-header"]',
                title: 'پایگاه مشتریان',
                content: 'مشتریان به‌صورت خودکار از تحلیل تماس‌ها ساخته می‌شوند.',
            },
            {
                selector: '[data-tour="customers-search"]',
                title: 'جستجوی مشتری',
                content: 'جستجو بر اساس نام، شرکت یا شماره تماس.',
            },
            {
                selector: '[data-tour="customers-grid"]',
                title: 'کارت‌های مشتری',
                content: 'کلیک روی هر کارت برای مشاهده تاریخچه تماس، امتیاز و بینش‌های هوش مشتری.',
            },
        ],
    },
    'employer.customers.show': {
        label: 'جزئیات مشتری',
        steps: [
            {
                selector: '[data-tour="customer-profile"]',
                title: 'پروفایل مشتری',
                content: 'اطلاعات تماس، امتیاز کلی و خلاصه هوش مشتری.',
            },
            {
                selector: '[data-tour="customer-timeline"]',
                title: 'تاریخچه تعامل',
                content: 'تماس‌ها و تحلیل‌های مرتبط با این مشتری.',
            },
        ],
    },
    'employer.manual-analyses.index': {
        label: 'آپلود دستی',
        steps: [
            {
                selector: '[data-tour="manual-upload-header"]',
                title: 'تحلیل دستی مکالمه',
                content: 'آپلود فایل صوتی برای تحلیل هوش مصنوعی — بدون نیاز به VoIP.',
            },
            {
                selector: '[data-tour="manual-upload-panel"]',
                title: 'آپلود فایل',
                content: 'فایل را بکشید یا انتخاب کنید، کارشناس را مشخص کنید و برای تحلیل ارسال کنید. موجودی کیف پول باید کافی باشد.',
            },
            {
                selector: '[data-tour="manual-samples"]',
                title: 'مکالمات نمونه',
                content: 'نمونه‌های آماده برای آشنایی با قابلیت‌های تحلیل.',
            },
            {
                selector: '[data-tour="manual-history"]',
                title: 'آپلودهای اخیر',
                content: 'تاریخچه آپلودهای تیم با فیلتر وضعیت و کارشناس.',
            },
        ],
    },
    'employer.processing-queue.index': {
        label: 'صف تحلیل',
        steps: [
            {
                selector: '[data-tour="queue-header"]',
                title: 'صف پردازش',
                content: 'پیگیری لحظه‌ای وضعیت تحلیل فایل‌های صوتی آپلودشده.',
            },
            {
                selector: '[data-tour="queue-stats"]',
                title: 'آمار صف',
                content: 'تعداد در صف، در حال پردازش، تکمیل‌شده و ناموفق.',
            },
            {
                selector: '[data-tour="queue-filters"]',
                title: 'فیلتر و جستجو',
                content: 'جستجو بر اساس نام فایل یا فیلتر وضعیت.',
            },
            {
                selector: '[data-tour="queue-table"]',
                title: 'لیست کارها',
                content: 'جزئیات هر کار پردازش و لینک به نتیجه تحلیل.',
            },
        ],
    },
    'employer.crm.index': {
        label: 'CRM',
        steps: [
            {
                selector: '[data-tour="crm-header"]',
                title: 'یکپارچه‌سازی CRM',
                content: 'اتصال CRM برای شناسایی تماس‌گیرنده و غنی‌سازی هوش مشتری.',
            },
            {
                selector: '[data-tour="crm-connections"]',
                title: 'اتصالات',
                content: 'وضعیت اتصال هر ارائه‌دهنده CRM و فعال بودن جستجوی مخاطب.',
            },
        ],
    },
    'employer.voip.index': {
        label: 'خطوط تلفنی',
        steps: [
            {
                selector: '[data-tour="voip-header"]',
                title: 'VoIP',
                content: 'اتصال سیستم تلفنی برای دریافت تماس‌های ورودی در فضای کارشناس.',
            },
            {
                selector: '[data-tour="voip-guide"]',
                title: 'راهنمای وب‌هوک',
                content: 'آدرس وب‌هوک و پارامترهای لازم برای ارسال رویداد تماس.',
            },
            {
                selector: '[data-tour="voip-stats"]',
                title: 'آمار تماس',
                content: 'تماس‌های امروز، ماه جاری و اتصالات فعال.',
            },
            {
                selector: '[data-tour="voip-connections"]',
                title: 'اتصالات VoIP',
                content: 'جزئیات هر خط و آدرس وب‌هوک اختصاصی.',
            },
        ],
    },
    'employer.reports.index': {
        label: 'گزارش‌های مدیریتی',
        steps: [
            {
                selector: '[data-tour="page-header"]',
                title: 'گزارش مدیریتی',
                content: 'داشبورد تصمیم‌گیری: KPI، نمودارها و رتبه‌بندی تیم. خروجی CSV/Excel/PDF از بالای صفحه.',
            },
            {
                selector: '[data-tour="report-filters"]',
                title: 'فیلتر گزارش',
                content: 'بازه زمانی، انتخاب کارشناسان و حالت مقایسه در نمودارها.',
            },
            {
                selector: '[data-tour="report-summary"]',
                title: 'خلاصه مدیریتی',
                content: 'جمع‌بندی متنی وضعیت بر اساس داده‌های فیلترشده.',
            },
            {
                selector: '[data-tour="report-kpis"]',
                title: 'شاخص‌های کلیدی',
                content: 'تماس، تحلیل، کیفیت، لید، نگرانی و هزینه AI.',
            },
            {
                selector: '[data-tour="report-charts"]',
                title: 'نمودارها',
                content: 'روند فعالیت، کیفیت، لید، نگرانی‌ها و مصرف AI. روی بخش‌های نمودار کلیک کنید برای drill-down.',
            },
            {
                selector: '[data-tour="report-rankings"]',
                title: 'رتبه‌بندی کارشناسان',
                content: 'بهترین کیفیت، بیشترین تحلیل، لید و عملکرد کلی.',
            },
        ],
    },
    'employer.wallet.index': {
        label: 'اعتبار هوش مصنوعی',
        steps: [
            {
                selector: '[data-tour="page-header"]',
                title: 'اعتبار AI',
                content: 'مدیریت موجودی کیف پول و پایش مصرف تحلیل‌های هوش مصنوعی.',
            },
            {
                selector: '[data-tour="wallet-balance"]',
                title: 'موجودی فعلی',
                content: 'اعتبار باقی‌مانده و تخمین روزهای مانده بر اساس مصرف اخیر.',
            },
            {
                selector: '[data-tour="wallet-stats"]',
                title: 'مصرف ماه جاری',
                content: 'تحلیل‌ها، هزینه، توکن و میانگین هزینه هر تحلیل.',
            },
            {
                selector: '[data-tour="wallet-charts"]',
                title: 'نمودارهای مصرف',
                content: 'روند روزانه تحلیل و هزینه، توکن‌ها و هزینه ماهانه.',
            },
            {
                selector: '[data-tour="wallet-transactions"]',
                title: 'تراکنش‌ها',
                content: 'واریز، مصرف و تعدیل‌های کیف پول.',
            },
        ],
    },
};

const navSteps = [
    ['employer.dashboard', 'داشبورد', 'نقطه شروع مدیر: نمای کلی تیم، عملکرد کارشناسان و فعالیت اخیر.'],
    ['employer.intelligence.performance', 'عملکرد کارشناسان', 'رتبه‌بندی، مقایسه و مربیگری تیم تماس.'],
    ['employer.intelligence.index', 'تحلیل تماس‌ها', 'فهرست تمام تحلیل‌ها با فیلتر و جزئیات هر مکالمه.'],
    ['employer.employees.index', 'کارشناسان', 'مدیریت اعضای تیم و دسترسی‌ها.'],
    ['employer.customers.index', 'مشتریان', 'هوش مشتری ساخته‌شده از تماس‌ها.'],
    ['employer.manual-analyses.index', 'آپلود دستی', 'تحلیل فایل صوتی بدون VoIP.'],
    ['employer.processing-queue.index', 'صف تحلیل', 'پیگیری وضعیت پردازش فایل‌های در صف.'],
    ['employer.crm.index', 'CRM', 'اتصال سیستم ارتباط با مشتری.'],
    ['employer.voip.index', 'خطوط تلفنی', 'اتصال VoIP و وب‌هوک تماس.'],
    ['employer.reports.index', 'گزارش‌های مدیریتی', 'گزارش جامع برای تصمیم‌گیری.'],
    ['employer.wallet.index', 'اعتبار هوش مصنوعی', 'موجودی و مصرف اعتبار تحلیل AI.'],
];

/** @returns {TourStep[]} */
export function buildFullEmployerTour() {
    /** @type {TourStep[]} */
    const steps = [
        {
            center: true,
            title: 'به پنل کارفرما خوش آمدید',
            content: 'این راهنمای تعاملی همه منوها و بخش‌های مهم را مرحله‌به‌مرحله معرفی می‌کند. می‌توانید هر زمان رد کنید یا بعداً از دکمه راهنما ادامه دهید.',
        },
        {
            selector: '[data-tour="sidebar"]',
            title: 'منوی اصلی',
            content: 'از این نوار برای جابه‌جایی بین بخش‌های برنامه استفاده کنید. در ادامه هر منو را جداگانه می‌بینیم.',
        },
        {
            selector: '[data-tour="topbar-theme"]',
            title: 'حالت تاریک/روشن',
            content: 'ظاهر برنامه را مطابق ترجیح خود تغییر دهید.',
        },
    ];

    navSteps.forEach(([route, title, content]) => {
        steps.push({
            selector: `[data-tour-nav="${route}"]`,
            title,
            content,
            route,
        });
    });

    steps.push({
        fab: true,
        title: 'دکمه راهنما',
        content: 'هر زمان بخواهید آموزش همان صفحه را ببینید، روی این دکمه بزنید. راهنمای جزئیات هر بخش همیشه از اینجا در دسترس است.',
        placement: 'top',
        align: 'start',
    });

    steps.push({
        center: true,
        title: 'پایان راهنمای کلی',
        content: 'آشنایی با پنل تمام شد. برای شروع کار، از منوی کناری به بخش مورد نظر بروید یا روی «راهنما» بزنید تا آموزش همان صفحه را ببینید.',
    });

    return steps;
}

export const FULL_TOUR_ID = 'employer-full';

/** @type {Array<{ pattern: RegExp, route: string }>} */
export const employerRouteMatchers = [
    { pattern: /^\/app\/?$/, route: 'employer.dashboard' },
    { pattern: /^\/app\/employees\/create\/?$/, route: 'employer.employees.create' },
    { pattern: /^\/app\/employees\/\d+\/edit\/?$/, route: 'employer.employees.edit' },
    { pattern: /^\/app\/employees\/?$/, route: 'employer.employees.index' },
    { pattern: /^\/app\/intelligence\/performance\/\d+\/?$/, route: 'employer.intelligence.performance.show' },
    { pattern: /^\/app\/intelligence\/performance\/?$/, route: 'employer.intelligence.performance' },
    { pattern: /^\/app\/intelligence\/\d+\/?$/, route: 'employer.intelligence.show' },
    { pattern: /^\/app\/intelligence\/?$/, route: 'employer.intelligence.index' },
    { pattern: /^\/app\/manual-analyses\/\d+\/?$/, route: 'employer.manual-analyses.show' },
    { pattern: /^\/app\/manual-analyses\/?$/, route: 'employer.manual-analyses.index' },
    { pattern: /^\/app\/processing-queue\/\d+\/?$/, route: 'employer.processing-queue.show' },
    { pattern: /^\/app\/processing-queue\/?$/, route: 'employer.processing-queue.index' },
    { pattern: /^\/app\/customers\/\d+\/?$/, route: 'employer.customers.show' },
    { pattern: /^\/app\/customers\/?$/, route: 'employer.customers.index' },
    { pattern: /^\/app\/crm\/?$/, route: 'employer.crm.index' },
    { pattern: /^\/app\/voip\/?$/, route: 'employer.voip.index' },
    { pattern: /^\/app\/reports\/?$/, route: 'employer.reports.index' },
    { pattern: /^\/app\/wallet\/?$/, route: 'employer.wallet.index' },
];

export function resolveEmployerRoute(pathname) {
    const path = (pathname || window.location.pathname).replace(/\/+$/, '') || '/app';

    for (const { pattern, route } of employerRouteMatchers) {
        if (pattern.test(path)) {
            return route;
        }
    }

    return window.__employerOnboarding?.currentRoute ?? null;
}

export function tourForRoute(route) {
    if (! route) {
        return null;
    }

    if (employerPageTours[route]) {
        return employerPageTours[route];
    }

    if (route === 'employer.intelligence.performance.show') {
        return employerPageTours['employer.intelligence.performance'];
    }

    if (route === 'employer.intelligence.show') {
        return {
            label: 'جزئیات تحلیل',
            steps: [
                {
                    selector: '[data-tour="analysis-detail"]',
                    title: 'جزئیات تحلیل تماس',
                    content: 'امتیاز، خلاصه، نگرانی‌ها، احساسات و پیشنهادهای بهبود این مکالمه.',
                },
            ],
        };
    }

    if (route === 'employer.manual-analyses.show' || route === 'employer.processing-queue.show') {
        return {
            label: 'جزئیات',
            steps: [
                {
                    center: true,
                    title: 'صفحه جزئیات',
                    content: 'وضعیت پردازش، فایل صوتی و نتیجه تحلیل را در این صفحه ببینید.',
                },
            ],
        };
    }

    return null;
}
