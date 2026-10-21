@php
    $partners = [
        [
            'file' => 'nova.png',
            'alt' => 'نوا',
            'modifier' => 'nova',
            'width' => 489,
            'height' => 263,
        ],
        [
            'file' => 'haqiqat.png',
            'alt' => 'دوخت حقیقت',
            'modifier' => 'haqiqat',
            'width' => 497,
            'height' => 127,
        ],
        [
            'file' => 'tolou-arian.png',
            'alt' => 'طلوع آرین',
            'modifier' => 'tolou',
            'width' => 721,
            'height' => 296,
        ],
    ];
@endphp

<section id="partners" class="partners" data-partners data-count="{{ count($partners) }}" aria-labelledby="partners-title" aria-roledescription="کاروسل">
    <div class="partners__wash" aria-hidden="true">
        <span class="partners__blob partners__blob--start"></span>
        <span class="partners__blob partners__blob--end"></span>
        <svg class="partners__lines" viewBox="0 0 1200 420" preserveAspectRatio="xMidYMid slice">
            <path d="M-20 90C80 40 150 150 280 80" />
            <path d="M920 300C1040 250 1120 360 1240 290" />
        </svg>
    </div>

    <div class="partners__inner">
        <p class="partners__kicker">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                <path stroke-linecap="round" d="M8.2 19.2v-1.15A3.05 3.05 0 0 1 11.25 15h.1" />
                <path stroke-linecap="round" d="M4.4 19.2v-1.05A2.85 2.85 0 0 1 7.2 15.3" />
                <circle cx="10.1" cy="8.3" r="2.15" />
                <path stroke-linecap="round" d="M15.8 19.2v-1.15A3.05 3.05 0 0 0 12.75 15h-.1" />
                <path stroke-linecap="round" d="M19.6 19.2v-1.05A2.85 2.85 0 0 0 16.8 15.3" />
                <circle cx="13.9" cy="8.3" r="2.15" />
            </svg>
            همکاری با شرکت‌ها
        </p>
        <h2 id="partners-title" class="partners__title">همراهان مسیر هوشمندسازی</h2>
        <p class="partners__lead">سازمان‌ها و تیم‌های حرفه‌ای برای تحلیل هوشمند تماس‌ها و بهبود عملکرد، آوایار را انتخاب کرده‌اند.</p>

        <div class="partners__viewport" data-partners-viewport>
            <ul class="partners__track" data-partners-track>
                @foreach (range(1, 3) as $copy)
                    @foreach ($partners as $partner)
                        <li class="partners__card" data-partner-copy="{{ $copy }}">
                            <img
                                class="partners__logo partners__logo--{{ $partner['modifier'] }}"
                                src="{{ asset('images/landing/partners/'.$partner['file']) }}"
                                width="{{ $partner['width'] }}"
                                height="{{ $partner['height'] }}"
                                alt="{{ $partner['alt'] }}"
                                draggable="false"
                                decoding="async"
                            >
                        </li>
                    @endforeach
                @endforeach
            </ul>
        </div>

        <div class="partners__controls">
            <button type="button" class="partners__arrow" data-partners-prev aria-label="لوگوی قبلی">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6" />
                </svg>
            </button>
            <div class="partners__dots" role="group" aria-label="انتخاب شرکت">
                @foreach ($partners as $index => $partner)
                    <button
                        type="button"
                        class="partners__dot"
                        data-partners-dot="{{ $index }}"
                        aria-label="{{ $partner['alt'] }}"
                    ></button>
                @endforeach
            </div>
            <button type="button" class="partners__arrow" data-partners-next aria-label="لوگوی بعدی">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 6l-6 6 6 6" />
                </svg>
            </button>
        </div>

        <p class="partners__note">و ده‌ها تیم دیگر برای تحلیل هوشمند تماس‌ها به آوایار اعتماد کرده‌اند.</p>
        <p class="partners__sr" data-partners-status aria-live="polite"></p>
    </div>
</section>
