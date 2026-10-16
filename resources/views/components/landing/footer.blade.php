    <footer class="site-footer">
        <div class="site-footer__inner">
            <div class="site-footer__grid">
                <div class="site-footer__brand">
                    <a href="{{ route('home') }}" class="site-footer__logo">
                        <img src="{{ asset('images/journey/avayar-logo.webp') }}" width="480" height="151" alt="آوایار" decoding="async">
                        <x-landing.brand-mark aria-hidden="true" />
                    </a>
                    <p class="site-footer__lead">تحلیل تماس‌های پایان‌یافته برای رشد تیم فروش، پشتیبانی و کیفیت. خلاصه، احساس مشتری، کیفیت لید و کوچینگ در یک داشبورد.</p>
                </div>

                <nav class="site-footer__col" aria-label="دسترسی سریع">
                    <h2>دسترسی سریع</h2>
                    <ul class="site-footer__links">
                        @foreach ([
                            ['#sample-call', 'ویژگی‌ها'],
                            ['#journey', 'مسیر تماس'],
                            ['#challenges', 'چالش‌ها'],
                            ['#sample-call', 'نمونه تحلیل'],
                            ['#coaching', 'کوچینگ'],
                            ['#faq', 'سوالات متداول'],
                        ] as [$href, $label])
                            <li>
                                <a href="{{ $href }}">
                                    {{ $label }}
                                    <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M12 5 7 10l5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>

                <div class="site-footer__col">
                    <h2>راه‌های ارتباطی</h2>
                    <ul class="site-footer__contacts">
                        <li>
                            <button type="button" data-consult-open>
                                <span class="site-footer__icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M8 10.5h8M8 14h5" /><path d="M7 6.5h10a2 2 0 0 1 2 2v7.2a2 2 0 0 1-2 2H10l-3.2 2.2v-2.2H7a2 2 0 0 1-2-2V8.5a2 2 0 0 1 2-2Z" /></svg>
                                </span>
                                <span>درخواست مشاوره<small>گفتگو درباره نیاز تیم فروش، پشتیبانی یا کیفیت</small></span>
                            </button>
                        </li>
                        <li>
                            @auth
                                <a href="{{ auth()->user()->portalRoute() }}">
                                    <span class="site-footer__icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="8.5" r="2.6" /><path d="M7.2 17.4c.7-2.2 2.5-3.3 4.8-3.3s4.1 1.1 4.8 3.3" /></svg>
                                    </span>
                                    <span>پنل کاربری<small>ورود به فضای کاری شما</small></span>
                                </a>
                            @else
                                <a href="{{ route('login') }}">
                                    <span class="site-footer__icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="8.5" r="2.6" /><path d="M7.2 17.4c.7-2.2 2.5-3.3 4.8-3.3s4.1 1.1 4.8 3.3" /></svg>
                                    </span>
                                    <span>ورود به پنل<small>فضای مدیر و کارشناس</small></span>
                                </a>
                            @endauth
                        </li>
                        <li>
                            <a href="#faq">
                                <span class="site-footer__icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="8" /><path d="M9.6 9.4a2.4 2.4 0 1 1 3.3 2.2c-.6.3-.9.8-.9 1.4V13.4" /><path d="M12 16.6h.01" /></svg>
                                </span>
                                <span>پرسش‌های اتصال<small>زمان تحلیل، خروجی‌ها و CRM</small></span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="site-footer__bar">
                <p class="site-footer__copy">© تمامی حقوق برای آوایار محفوظ است.</p>
                <svg class="site-footer__wave" viewBox="0 0 260 36" fill="none" aria-hidden="true">
                    <path d="M4 24c28-16 48-16 76-4 22 10 40 12 68 2 24-8 48-8 76 6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                    <circle cx="18" cy="22" r="1.4" fill="currentColor" />
                    <circle cx="48" cy="16" r="1.4" fill="currentColor" />
                    <circle cx="86" cy="18" r="1.4" fill="currentColor" />
                    <circle cx="132" cy="20" r="1.4" fill="currentColor" />
                    <circle cx="178" cy="16" r="1.4" fill="currentColor" />
                    <circle cx="226" cy="22" r="1.4" fill="currentColor" />
                </svg>
                <a class="site-footer__credit" href="https://mirka.agency/" target="_blank" rel="noopener noreferrer">محصولی از میرکا💎</a>
            </div>
        </div>
    </footer>
