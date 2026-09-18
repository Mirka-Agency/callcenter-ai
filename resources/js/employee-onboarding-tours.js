/** @typedef {{ selector?: string, title: string, content: string, placement?: string, align?: string, route?: string, center?: boolean, fab?: boolean }} TourStep */

/** @type {Record<string, { label: string, steps: TourStep[] }>} */
export const employeePageTours = {
    'employee.dashboard': {
        label: 'داشبورد',
        steps: [
            {
                selector: '[data-tour="dashboard-hero"]',
                title: 'داشبورد عملکرد',
                content: 'خلاصه شخصی شما: امتیاز کلی، خوش‌آمدگویی و نمای سریع از وضعیت اخیر.',
            },
            {
                selector: '[data-tour="dashboard-stats"]',
                title: 'شاخص‌های کلیدی',
                content: 'پیشرفت هفتگی و ماهانه، تعداد تماس، میانگین امتیاز و رضایت مشتری در یک نگاه.',
            },
            {
                selector: '[data-tour="dashboard-trends"]',
                title: 'روند عملکرد',
                content: 'نمودارهای امتیاز و رضایت مشتری در ۳۰ روز اخیر — برای دیدن روند بهبود یا افت.',
            },
            {
                selector: '[data-tour="dashboard-summary"]',
                title: 'عملکرد و دستاوردها',
                content: 'خلاصه تحلیل‌های اخیر، توکن مصرف‌شده و نشان‌های کسب‌شده از عملکرد شما.',
            },
            {
                selector: '[data-tour="dashboard-strengths"]',
                title: 'نقاط قوت و بهبود',
                content: 'مواردی که بیشتر در تماس‌های شما تکرار شده‌اند — هم نقاط قوت و هم حوزه‌های تمرکز.',
            },
            {
                selector: '[data-tour="dashboard-recommendations"]',
                title: 'پیشنهادهای مربیگری',
                content: 'اقدامات پیشنهادی با اولویت بر اساس گفتگوهای اخیر شما.',
            },
        ],
    },
    'employee.performance': {
        label: 'عملکرد من',
        steps: [
            {
                selector: '[data-tour="performance-hero"]',
                title: 'پروفایل عملکرد',
                content: 'امتیاز کلی، روند کیفیت و رضایت، و خلاصه مدیریتی بر اساس بازه انتخاب‌شده.',
            },
            {
                selector: '[data-tour="performance-filters"]',
                title: 'بازه زمانی',
                content: 'بازه را عوض کنید تا آمار و نمودارها متناسب با همان دوره به‌روز شوند.',
            },
            {
                selector: '[data-tour="performance-stats"]',
                title: 'شاخص‌های عملکرد',
                content: 'تماس‌های پاسخ‌داده، مدت مکالمه، امتیاز سرنخ، رضایت و بهترین امتیاز.',
            },
            {
                selector: '[data-tour="performance-charts"]',
                title: 'نمودارهای عملکرد',
                content: 'روند امتیاز، حجم تماس و ابعاد کیفیت مکالمه در بازه فعلی.',
            },
        ],
    },
    'employee.calls': {
        label: 'تماس‌های من',
        steps: [
            {
                selector: '[data-tour="page-header"]',
                title: 'تماس‌های تحلیل‌شده',
                content: 'همه مکالمات تحلیل‌شده شما با فیلتر پیشرفته و لینک سریع به بارگذاری تماس جدید.',
            },
            {
                selector: '[data-tour="call-filters"]',
                title: 'فیلترها',
                content: 'بازه زمانی، وضعیت تماس، جهت، مدت و فیلترهای سریع. برای بازه دلخواه تاریخ‌ها را انتخاب و تایید کنید.',
            },
            {
                selector: '[data-tour="calls-stats"]',
                title: 'خلاصه فیلترشده',
                content: 'تعداد کل تماس‌ها، تعداد تحلیل، میانگین امتیاز، سرنخ، رضایت و آمار تماس در بازه فعلی.',
            },
            {
                selector: '[data-tour="calls-charts"]',
                title: 'نمودارهای تماس',
                content: 'روند کیفیت، حجم تحلیل، توزیع سرنخ، احساسات و نگرانی‌های پرتکرار.',
            },
            {
                selector: '[data-tour="calls-list"]',
                title: 'لیست تماس‌ها',
                content: 'هر ردیف قابل کلیک است. جستجو و مرتب‌سازی ستون‌ها از همین بخش.',
            },
        ],
    },
    'employee.uploads': {
        label: 'آپلود تماس',
        steps: [
            {
                selector: '[data-tour="upload-header"]',
                title: 'بارگذاری تماس',
                content: 'نقطه شروع تحلیل: فایل صوتی را بارگذاری کنید و وضعیت پردازش را پیگیری کنید.',
            },
            {
                selector: '[data-tour="upload-zone"]',
                title: 'آپلود فایل',
                content: 'فایل MP3 یا WAV را بکشید و رها کنید یا انتخاب کنید. در صورت نیاز اطلاعات تماس را تکمیل کنید.',
            },
            {
                selector: '[data-tour="upload-samples"]',
                title: 'نمونه مکالمات',
                content: 'برای آشنایی با سیستم، یک نمونه فروش را بارگذاری کنید تا تحلیل واقعی ببینید.',
            },
            {
                selector: '[data-tour="upload-history"]',
                title: 'تاریخچه بارگذاری',
                content: 'تماس‌های اخیر شما با جستجو و فیلتر بازه. برای جزئیات روی هر کارت کلیک کنید.',
            },
        ],
    },
    'employee.coaching': {
        label: 'مربیگری فروش',
        steps: [
            {
                selector: '[data-tour="page-header"]',
                title: 'مربیگری فروش',
                content: 'برنامه رشد شخصی بر اساس تحلیل تماس‌ها — نقاط قوت، بهبود و اقدام عملی.',
            },
            {
                selector: '[data-tour="coaching-hero"]',
                title: 'خلاصه مربیگری',
                content: 'جمع‌بندی وضعیت شما و میانگین کیفیت در بازه انتخاب‌شده.',
            },
            {
                selector: '[data-tour="coaching-filters"]',
                title: 'بازه زمانی',
                content: 'بازه را تغییر دهید تا بینش‌های مربیگری متناسب با همان دوره به‌روز شوند.',
            },
            {
                selector: '[data-tour="coaching-stats"]',
                title: 'شاخص‌های مربیگری',
                content: 'تعداد تحلیل، میانگین امتیاز، سرنخ و رضایت در بازه فعلی.',
            },
            {
                selector: '[data-tour="coaching-insights"]',
                title: 'بینش‌های رشد',
                content: 'نقاط قوت، حوزه‌های بهبود و تمرکزهای مربیگری با اولویت.',
            },
        ],
    },
    'employee.activity': {
        label: 'فعالیت اخیر',
        steps: [
            {
                selector: '[data-tour="page-header"]',
                title: 'فعالیت اخیر',
                content: 'رویدادهای تحلیل، بارگذاری و بازخورد شما در یک خط زمانی.',
            },
            {
                selector: '[data-tour="activity-hero"]',
                title: 'خلاصه فعالیت',
                content: 'تعداد رویدادها، تحلیل‌ها و بارگذاری‌ها در بازه انتخاب‌شده.',
            },
            {
                selector: '[data-tour="activity-filters"]',
                title: 'بازه زمانی',
                content: 'بازه را عوض کنید تا خط زمانی و آمار متناسب به‌روز شوند.',
            },
            {
                selector: '[data-tour="activity-stats"]',
                title: 'شاخص‌های فعالیت',
                content: 'تحلیل‌های دریافت‌شده، بارگذاری‌ها، بازخوردها و میانگین امتیاز.',
            },
            {
                selector: '[data-tour="activity-chart"]',
                title: 'حجم روزانه',
                content: 'نمودار تحلیل‌ها و بارگذاری‌ها به تفکیک روز.',
            },
            {
                selector: '[data-tour="activity-timeline"]',
                title: 'خط زمانی',
                content: 'فیلتر نوع رویداد و جستجو. برای جزئیات روی هر رویداد کلیک کنید.',
            },
        ],
    },
    'employee.customers.index': {
        label: 'مشتریان',
        steps: [
            {
                center: true,
                title: 'پایگاه مشتریان',
                content: 'این بخش شرکت‌ها و اشخاص را جداگانه نشان می‌دهد. در ادامه هر کدام را می‌بینیم.',
            },
            {
                selector: '[data-tour="customers-hub-stats"]',
                title: 'آمار کلی',
                content: 'تعداد شرکت‌ها، اشخاص و تماس‌های مرتبط با تماس‌های تحلیل‌شده شما.',
            },
            {
                selector: '[data-tour="customers-hub-companies"]',
                title: 'ورود به شرکت‌ها',
                content: 'شرکت‌های مشتری که از تماس‌های شما شناسایی شده‌اند.',
            },
            {
                selector: '[data-tour="customers-hub-contacts"]',
                title: 'ورود به اشخاص',
                content: 'افراد مرتبط با تماس‌های تحلیل‌شده — با یا بدون شرکت.',
            },
            {
                center: true,
                title: 'لیست شرکت‌ها',
                content: 'حالا بخش شرکت‌ها را باز می‌کنیم.',
                route: 'employee.customers.companies.index',
            },
            {
                selector: '[data-tour="customers-companies-search"]',
                title: 'جستجوی شرکت',
                content: 'جستجو بر اساس نام، صنعت، تلفن یا ایمیل.',
            },
            {
                selector: '[data-tour="customers-companies-grid"]',
                title: 'کارت‌های شرکت',
                content: 'برای دیدن اشخاص و آمار هر شرکت روی کارت کلیک کنید.',
            },
            {
                center: true,
                title: 'لیست اشخاص',
                content: 'حالا بخش اشخاص را می‌بینیم.',
                route: 'employee.customers.contacts.index',
            },
            {
                selector: '[data-tour="customers-contacts-search"]',
                title: 'جستجوی شخص',
                content: 'جستجو بر اساس نام، شرکت یا شماره تماس.',
            },
            {
                selector: '[data-tour="customers-contacts-grid"]',
                title: 'کارت‌های شخص',
                content: 'برای تاریخچه تماس و بینش‌ها روی هر کارت کلیک کنید.',
            },
        ],
    },
    'employee.customers.companies.index': {
        label: 'شرکت‌ها',
        steps: [
            {
                selector: '[data-tour="page-header"]',
                title: 'لیست شرکت‌ها',
                content: 'شرکت‌های شناسایی‌شده از تماس‌های تحلیل‌شده شما.',
            },
            {
                selector: '[data-tour="customers-companies-search"]',
                title: 'جستجو',
                content: 'فیلتر سریع شرکت‌ها.',
            },
            {
                selector: '[data-tour="customers-companies-grid"]',
                title: 'کارت‌های شرکت',
                content: 'اشخاص و آمار تجمیعی هر شرکت.',
            },
        ],
    },
    'employee.customers.contacts.index': {
        label: 'اشخاص',
        steps: [
            {
                selector: '[data-tour="page-header"]',
                title: 'لیست اشخاص',
                content: 'افراد مرتبط با تماس‌های تحلیل‌شده شما.',
            },
            {
                selector: '[data-tour="customers-contacts-search"]',
                title: 'جستجو',
                content: 'جستجو بر اساس نام، شرکت یا شماره.',
            },
            {
                selector: '[data-tour="customers-contacts-grid"]',
                title: 'کارت‌های شخص',
                content: 'برای جزئیات تماس و بینش‌ها روی کارت کلیک کنید.',
            },
        ],
    },
    'employee.customers.companies.show': {
        label: 'جزئیات شرکت',
        steps: [
            {
                selector: '[data-tour="company-profile"]',
                title: 'پروفایل شرکت',
                content: 'اطلاعات و وضعیت کلی این شرکت.',
            },
            {
                selector: '[data-tour="company-stats"]',
                title: 'آمار شرکت',
                content: 'اشخاص، تماس‌ها و میانگین امتیاز.',
            },
            {
                selector: '[data-tour="company-contacts"]',
                title: 'اشخاص شرکت',
                content: 'افراد متصل به این شرکت.',
            },
            {
                selector: '[data-tour="company-analytics"]',
                title: 'تحلیل تجمیعی',
                content: 'نمودارهای امتیاز و احساسات بر اساس تماس‌های اشخاص.',
            },
        ],
    },
    'employee.processing-queue.index': {
        label: 'صف تحلیل',
        steps: [
            {
                selector: '[data-tour="queue-header"]',
                title: 'صف پردازش',
                content: 'پیگیری لحظه‌ای وضعیت بارگذاری و تحلیل تماس‌های شما.',
            },
            {
                selector: '[data-tour="queue-stats"]',
                title: 'آمار صف',
                content: 'تعداد کل، در صف، در حال پردازش، تکمیل‌شده و ناموفق.',
            },
            {
                selector: '[data-tour="queue-filters"]',
                title: 'فیلتر و جستجو',
                content: 'جستجو بر اساس نام فایل یا فیلتر وضعیت.',
            },
            {
                selector: '[data-tour="queue-table"]',
                title: 'جدول صف',
                content: 'وضعیت هر فایل و لینک به جزئیات پردازش.',
            },
        ],
    },
};

const navSteps = [
    ['employee.dashboard', 'داشبورد', 'نقطه شروع کارشناس: عملکرد شخصی، روند و پیشنهادهای مربیگری.'],
    ['employee.performance', 'عملکرد من', 'جزئیات امتیاز، نمودارها و مقایسه با دوره قبل.'],
    ['employee.calls', 'تماس‌های من', 'فهرست تمام تحلیل‌ها با فیلتر و جزئیات هر مکالمه.'],
    ['employee.customers.index', 'مشتریان', 'شرکت‌ها و اشخاص — پروفایل خودکار از تماس‌های تحلیل‌شده شما.'],
    ['employee.uploads', 'آپلود تماس', 'بارگذاری فایل صوتی برای تحلیل هوش مصنوعی.'],
    ['employee.processing-queue.index', 'صف تحلیل', 'پیگیری وضعیت پردازش فایل‌های در صف.'],
    ['employee.coaching', 'مربیگری فروش', 'برنامه رشد و اقدامات عملی بر اساس تماس‌ها.'],
    ['employee.activity', 'فعالیت اخیر', 'خط زمانی تحلیل‌ها، بارگذاری‌ها و بازخوردها.'],
];

/** @returns {TourStep[]} */
export function buildFullEmployeeTour() {
    /** @type {TourStep[]} */
    const steps = [
        {
            center: true,
            title: 'به پنل کارشناس خوش آمدید',
            content: 'این راهنمای تعاملی منوها و بخش‌های مهم را مرحله‌به‌مرحله معرفی می‌کند. می‌توانید هر زمان رد کنید یا بعداً از دکمه راهنما ادامه دهید.',
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
        content: 'آشنایی با پنل تمام شد. برای شروع، تماس بارگذاری کنید یا از منوی کناری به بخش مورد نظر بروید.',
    });

    return steps;
}

export const FULL_TOUR_ID = 'employee-full';

/** @type {Array<{ pattern: RegExp, route: string }>} */
export const employeeRouteMatchers = [
    { pattern: /^\/workspace\/?$/, route: 'employee.dashboard' },
    { pattern: /^\/workspace\/performance\/?$/, route: 'employee.performance' },
    { pattern: /^\/workspace\/uploads\/\d+\/?$/, route: 'employee.uploads.show' },
    { pattern: /^\/workspace\/uploads\/?$/, route: 'employee.uploads' },
    { pattern: /^\/workspace\/processing-queue\/\d+\/?$/, route: 'employee.processing-queue.show' },
    { pattern: /^\/workspace\/processing-queue\/?$/, route: 'employee.processing-queue.index' },
    { pattern: /^\/workspace\/calls\/\d+\/?$/, route: 'employee.calls.show' },
    { pattern: /^\/workspace\/calls\/?$/, route: 'employee.calls' },
    { pattern: /^\/workspace\/customers\/companies\/?$/, route: 'employee.customers.companies.index' },
    { pattern: /^\/workspace\/customers\/contacts\/?$/, route: 'employee.customers.contacts.index' },
    { pattern: /^\/workspace\/customers\/companies\/\d+\/?$/, route: 'employee.customers.companies.show' },
    { pattern: /^\/workspace\/customers\/\d+\/edit\/?$/, route: 'employee.customers.edit' },
    { pattern: /^\/workspace\/customers\/\d+\/?$/, route: 'employee.customers.show' },
    { pattern: /^\/workspace\/customers\/?$/, route: 'employee.customers.index' },
    { pattern: /^\/workspace\/coaching\/?$/, route: 'employee.coaching' },
    { pattern: /^\/workspace\/activity\/?$/, route: 'employee.activity' },
    { pattern: /^\/workspace\/profile\/?$/, route: 'employee.profile.edit' },
];

export function resolveEmployeeRoute(pathname) {
    const path = (pathname || window.location.pathname).replace(/\/+$/, '') || '/workspace';

    for (const { pattern, route } of employeeRouteMatchers) {
        if (pattern.test(path)) {
            return route;
        }
    }

    return window.__employeeOnboarding?.currentRoute ?? null;
}

export function tourForRoute(route) {
    if (! route) {
        return null;
    }

    if (employeePageTours[route]) {
        return employeePageTours[route];
    }

    if (route === 'employee.calls.show') {
        return {
            label: 'جزئیات تماس',
            steps: [
                {
                    selector: '[data-tour="analysis-detail"]',
                    title: 'جزئیات تحلیل تماس',
                    content: 'امتیاز، خلاصه، نگرانی‌ها، احساسات و پیشنهادهای بهبود این مکالمه.',
                },
            ],
        };
    }

    if (route === 'employee.uploads.show' || route === 'employee.processing-queue.show') {
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

    if (route === 'employee.customers.show') {
        return {
            label: 'جزئیات شخص',
            steps: [
                {
                    selector: '[data-tour="customer-profile"]',
                    title: 'پروفایل شخص',
                    content: 'اطلاعات تماس، شرکت مرتبط و بینش‌های جمع‌آوری‌شده از مکالمات.',
                },
                {
                    selector: '[data-tour="customer-timeline"]',
                    title: 'تاریخچه تعامل',
                    content: 'تماس‌ها و تحلیل‌های مرتبط با این شخص.',
                },
            ],
        };
    }

    if (route === 'employee.customers.companies.show') {
        return employeePageTours['employee.customers.companies.show'];
    }

    return null;
}
