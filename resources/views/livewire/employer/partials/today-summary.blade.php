@php
    $qualityScore = is_numeric($teamKpis['average_quality_score'] ?? null)
        ? (float) $teamKpis['average_quality_score']
        : 0.0;
    $qualityLabel = $qualityScore > 0 ? $qualityScore : '—';
    $qualityWidth = max(0, min(100, $qualityScore));
    $dissatisfiedCount = count($sentimentCustomers['dissatisfied'] ?? []);
    $agentsNeedingProgress = (int) ($agentCardFeed['counts']['attention'] ?? 0);
    $highProbabilityOpportunities = collect($tradingOpportunities ?? [])
        ->filter(fn (array $opportunity): bool => is_numeric($opportunity['purchase_probability'] ?? null)
            && (int) $opportunity['purchase_probability'] >= 70)
        ->count();
@endphp

<section class="saas-today-summary" aria-labelledby="today-summary-title">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h2 id="today-summary-title" class="text-base font-semibold text-violet-950 dark:text-violet-50">خلاصه امروز</h2>
            <p class="mt-0.5 text-xs text-violet-700/80 dark:text-violet-300/80">تصویر فعلی تیم در ۳۰ روز اخیر</p>
        </div>
        <span class="rounded-full border border-violet-300/80 bg-white/70 px-2.5 py-1 text-xs font-medium text-violet-700 dark:border-violet-600 dark:bg-violet-950/60 dark:text-violet-200">
            امروز
        </span>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article class="saas-today-summary-item">
            <div class="saas-today-summary-icon saas-today-summary-icon--quality" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 17l6-6 4 4 8-8" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 7h7v7" />
                </svg>
            </div>
            <div class="min-w-0 flex-1">
                <p class="saas-today-summary-label">کیفیت تیم</p>
                <p class="saas-today-summary-value">{{ $qualityLabel }}</p>
                <div class="saas-today-summary-meter" aria-hidden="true">
                    <span style="width: {{ $qualityWidth }}%"></span>
                </div>
            </div>
        </article>

        <article class="saas-today-summary-item">
            <div class="saas-today-summary-icon saas-today-summary-icon--unhappy" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="12" cy="12" r="8" />
                    <path stroke-linecap="round" d="M8.5 16c1-.9 2.2-1.4 3.5-1.4s2.5.5 3.5 1.4" />
                    <path stroke-linecap="round" d="M9 10h.01M15 10h.01" />
                </svg>
            </div>
            <div class="min-w-0 flex-1">
                <p class="saas-today-summary-label">تعداد مشتری ناراضی</p>
                <p class="saas-today-summary-value">{{ number_format($dissatisfiedCount) }}</p>
            </div>
        </article>

        <article class="saas-today-summary-item">
            <div class="saas-today-summary-icon saas-today-summary-icon--agents" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 19v-1.2A3.8 3.8 0 0012.2 14H7.8A3.8 3.8 0 004 17.8V19" />
                    <circle cx="10" cy="8" r="3" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 11v6M16 14h6" />
                </svg>
            </div>
            <div class="min-w-0 flex-1">
                <p class="saas-today-summary-label">تعداد کارشناسان نیازمند پیشرفت</p>
                <p class="saas-today-summary-value">{{ number_format($agentsNeedingProgress) }}</p>
            </div>
        </article>

        <article class="saas-today-summary-item">
            <div class="saas-today-summary-icon saas-today-summary-icon--sales" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 8h16v11H4z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 8V6.5A2.5 2.5 0 0110.5 4h3A2.5 2.5 0 0116 6.5V8" />
                    <path stroke-linecap="round" d="M4 12h16" />
                </svg>
            </div>
            <div class="min-w-0 flex-1">
                <p class="saas-today-summary-label">تعداد فرصت فروش با احتمال بالا</p>
                <p class="saas-today-summary-value">{{ number_format($highProbabilityOpportunities) }}</p>
            </div>
        </article>
    </div>
</section>
