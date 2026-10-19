@php
    use App\Services\LandingCostCalculator;
    use App\Support\PersianNumber;

    $quote = app(LandingCostCalculator::class)->present();
    $fa = fn (int|float $value, int $decimals = 0): string => PersianNumber::format($value, $decimals) ?? '۰';
@endphp

<section id="cost-calculator" class="cost-calc" aria-labelledby="cost-calculator-title">
    <div class="cost-calc__inner">
        <div class="cost-calc__copy">
            <p class="cost-calc__kicker">
                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M10 2.4 11.3 7 16 8.2 11.3 9.5 10 14.1 8.7 9.5 4 8.2 8.7 7 10 2.4Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
                </svg>
                شفاف، سریع، متناسب با حجم تماس
            </p>
            <h2 id="cost-calculator-title" class="cost-calc__title">محاسبه هزینه <span class="is-accent">آوایار</span></h2>
            <p class="cost-calc__text">با ماشین‌حساب آوایار، هزینه تحلیل مکالمات را با عددهای تیم خود می‌بینید و مدل را متناسب با حجم تماس‌ها انتخاب می‌کنید.</p>
            <ul class="cost-calc__points">
                <li>
                    <span class="cost-calc__point-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M13 3 5.5 13.5H11L10 21l8.5-11.5H13L13 3Z" />
                        </svg>
                    </span>
                    <span>
                        <strong>محاسبه آنی</strong>
                        <span>با جابه‌جایی تعداد یا مدت تماس، مبلغ همان لحظه عوض می‌شود.</span>
                    </span>
                </li>
                <li>
                    <span class="cost-calc__point-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 3.5 19.5 7v5.2c0 4.2-3.1 6.8-7.5 8.3-4.4-1.5-7.5-4.1-7.5-8.3V7L12 3.5Z" />
                            <path d="M9 12.1 11 14.1 15.2 9.8" />
                        </svg>
                    </span>
                    <span>
                        <strong>تعرفه شفاف</strong>
                        <span>مبلغ از قیمت همان مدل‌هایی حساب می‌شود که در آوایار ثبت شده است.</span>
                    </span>
                </li>
                <li>
                    <span class="cost-calc__point-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="7" y="7" width="10" height="10" rx="2" />
                            <path d="M9 3.5h6M9 20.5h6M3.5 9v6M20.5 9v6" />
                        </svg>
                    </span>
                    <span>
                        <strong>انتخاب مدل</strong>
                        <span>مدل فعال را عوض کنید و تفاوت هزینه تحلیل را کنار هم ببینید.</span>
                    </span>
                </li>
            </ul>
        </div>

        <div class="cost-calc__stage" @if ($quote['models'] !== []) data-cost-calculator data-quote='@json($quote)' @endif>
            @if ($quote['models'] === [])
                <div class="cost-calc__card">
                    <p class="cost-calc__empty">تعرفه مدل‌ها هنوز در آوایار تنظیم نشده است. بعد از ثبت قیمت مدل در پنل مدیریت، این محاسبه فعال می‌شود.</p>
                </div>
            @else
                <div class="cost-calc__card">
                    <header class="cost-calc__card-head">
                        <div>
                            <h3>هزینه تحلیل این ماه</h3>
                            <p>تعداد تماس، مدت و مدل را مشخص کنید.</p>
                        </div>
                        <span class="cost-calc__card-mark" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="5" y="3.5" width="14" height="17" rx="2" />
                                <path d="M8.5 8h7M8.5 12h7M8.5 16h4" />
                            </svg>
                        </span>
                    </header>

                    <div class="cost-calc__metric">
                        <div class="cost-calc__metric-head">
                            <label for="cost-calls">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.2 4.2c.4-.8 1.4-1.1 2.2-.7l1.7.8c.7.3 1 1.1.8 1.8l-.5 1.7a1.6 1.6 0 0 1-1.1 1.1l-1.2.4a12.2 12.2 0 0 0 6.6 6.6l.4-1.2c.2-.6.8-1 1.4-1.1l1.7-.5c.7-.2 1.5.1 1.8.8l.8 1.7c.4.8.1 1.8-.7 2.2l-1.2.6c-.8.4-1.7.4-2.5 0C11.6 16.8 7.2 12.4 5.6 7.9c-.4-.8-.4-1.7 0-2.5l.6-1.2Z" />
                                </svg>
                                تعداد تماس‌ها
                            </label>
                            <span class="cost-calc__field">
                                <input id="cost-calls" data-calls-input inputmode="numeric" autocomplete="off" enterkeyhint="done" value="{{ $fa($quote['calls']) }}" aria-describedby="cost-calls-range">
                                <span>تماس</span>
                            </span>
                        </div>
                        <input id="cost-calls-range" class="cost-calc__range" data-calls-range type="range" min="0" max="15000" step="50" value="{{ $quote['calls'] }}" style="--fill: {{ $quote['callFill'] }}%" aria-label="تعداد تماس‌ها">
                    </div>

                    <div class="cost-calc__metric">
                        <div class="cost-calc__metric-head">
                            <label for="cost-minutes">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                    <circle cx="12" cy="12" r="8" />
                                    <path stroke-linecap="round" d="M12 8v4.2l2.6 1.6" />
                                </svg>
                                میانگین دقیقه هر تماس
                            </label>
                            <span class="cost-calc__field">
                                <input id="cost-minutes" data-minutes-input inputmode="decimal" autocomplete="off" enterkeyhint="done" value="{{ $fa($quote['minutes'], 0) }}" aria-describedby="cost-minutes-range">
                                <span>دقیقه</span>
                            </span>
                        </div>
                        <input id="cost-minutes-range" class="cost-calc__range" data-minutes-range type="range" min="0.5" max="10" step="0.1" value="{{ $quote['minutes'] }}" style="--fill: {{ $quote['minuteFill'] }}%" aria-label="میانگین دقیقه هر تماس">
                    </div>

                    <div class="cost-calc__metric cost-calc__metric--models">
                        <div class="cost-calc__metric-head">
                            <p id="cost-model-label">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3.2 13.4 8l4.8 1.4L13.4 10.8 12 15.6 10.6 10.8 5.8 9.4 10.6 8 12 3.2Z" />
                                    <path stroke-linecap="round" d="M17.5 14.2 18.2 16.4 20.4 17.1 18.2 17.8 17.5 20 16.8 17.8 14.6 17.1 16.8 16.4 17.5 14.2Z" />
                                </svg>
                                مدل هوش مصنوعی
                            </p>
                        </div>
                        <div class="cost-calc__models" role="radiogroup" aria-labelledby="cost-model-label">
                            @foreach ($quote['models'] as $model)
                                <label class="cost-calc__model">
                                    <input type="radio" name="cost-model" value="{{ $model['id'] }}" data-model @checked($model['id'] === $quote['selectedId'])>
                                    <span dir="auto">{{ $model['name'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="cost-calc__result">
                        <div>
                            <p class="cost-calc__result-label">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                    <path stroke-linecap="round" d="M5 19V11M10 19V7M15 19v-5M20 19V5" />
                                </svg>
                                مبلغ تقریبی ماه
                            </p>
                            <p class="cost-calc__total">
                                <strong data-total>{{ $fa($quote['total'], $quote['totalDecimals']) }}</strong>
                                <span>{{ $quote['unit'] }}</span>
                            </p>
                        </div>
                        <span class="cost-calc__coins" aria-hidden="true">
                            <svg viewBox="0 0 64 48" fill="none">
                                <ellipse cx="24" cy="30" rx="16" ry="8" fill="#ddd6fe" />
                                <ellipse cx="24" cy="26" rx="16" ry="8" fill="#c4b5fd" />
                                <ellipse cx="24" cy="22" rx="16" ry="8" fill="#a78bfa" />
                                <ellipse cx="40" cy="24" rx="14" ry="7" fill="#8b5cf6" />
                                <ellipse cx="40" cy="20" rx="14" ry="7" fill="#7c25fd" />
                                <path d="M30 20.2c1.8 1.2 5.2 1.8 10 0" stroke="#fff" stroke-width="1.4" stroke-linecap="round" opacity=".7" />
                            </svg>
                        </span>
                    </div>
                </div>

                @if (count($quote['models']) > 1)
                    <aside class="cost-calc__compare" aria-hidden="true">
                        <p>مقایسه تعرفه مدل‌ها</p>
                        <div class="cost-calc__bars">
                            @foreach (array_slice($quote['models'], 0, 4) as $model)
                                <span data-compare-bar="{{ $model['id'] }}" class="@if ($model['id'] === $quote['selectedId']) is-on @endif" style="height: {{ max(18, $model['share']) }}%"></span>
                            @endforeach
                        </div>
                    </aside>
                @endif
            @endif

            <p class="cost-calc__aside" aria-hidden="true">تعرفه را قبل از شروع ببینید</p>
        </div>
    </div>
</section>
