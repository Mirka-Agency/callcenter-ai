@php
    $qualityScore = is_numeric($teamKpis['average_quality_score'] ?? null)
        ? (float) $teamKpis['average_quality_score']
        : 0.0;
    $qualityLabel = $qualityScore > 0
        ? (fmod($qualityScore, 1.0) === 0.0 ? (string) (int) $qualityScore : rtrim(rtrim(number_format($qualityScore, 1, '.', ''), '0'), '.'))
        : '—';
    $qualityWidth = max(0, min(100, $qualityScore));

    $record = fn (string $name, string $url): array => ['name' => $name, 'url' => $url];

    $summaryLists = [
        'مشتری ناراضی' => collect($sentimentCustomers['dissatisfied'] ?? [])
            ->map(fn (array $customer): array => $record(
                $customer['customer'] ?? '—',
                route('employer.intelligence.show', $customer['analysis_id']),
            ))
            ->all(),
        'کارشناسان نیازمند پیشرفت' => $progressAgentCalls ?? [],
        'فرصت فروش با احتمال بالا' => collect($tradingOpportunities ?? [])
            ->filter(fn (array $opportunity): bool => is_numeric($opportunity['purchase_probability'] ?? null)
                && (int) $opportunity['purchase_probability'] >= 70)
            ->map(fn (array $opportunity): array => $record(
                $opportunity['customer'] ?? '—',
                route('employer.intelligence.show', $opportunity['analysis_id']),
            ))
            ->values()
            ->all(),
    ];
@endphp

<section class="saas-today-summary" aria-labelledby="today-summary-title">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h2 id="today-summary-title" class="text-base font-semibold text-violet-950 dark:text-violet-50">خلاصه امروز</h2>
            <p class="mt-0.5 text-xs text-violet-700/80 dark:text-violet-300/80">آنچه امروز باید ببینید</p>
        </div>
        <span class="rounded-full border border-violet-300/80 bg-white/70 px-2.5 py-1 text-xs font-medium text-violet-700 dark:border-violet-600 dark:bg-violet-950/60 dark:text-violet-200">
            امروز
        </span>
    </div>

    <div class="mt-4 grid grid-cols-1 items-start gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article class="saas-today-summary-item">
            <div class="saas-today-summary-icon saas-today-summary-icon--quality" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 17l6-6 4 4 8-8" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 7h7v7" />
                </svg>
            </div>
            <div class="min-w-0 flex-1">
                <p class="saas-today-summary-label">کیفیت تیم</p>
                <p class="saas-today-summary-value">
                    {{ $qualityLabel }}<span class="saas-today-summary-scale">/۱۰۰</span>
                </p>
                <div class="saas-today-summary-meter" aria-hidden="true">
                    <span style="width: {{ $qualityWidth }}%"></span>
                </div>
            </div>
        </article>

        @foreach ($summaryLists as $label => $rows)
            <article class="saas-today-summary-item saas-today-summary-item--list">
                <p class="saas-today-summary-label">{{ $label }}</p>
                @if ($rows === [])
                    <p class="mt-3 text-sm text-zinc-400">موردی نیست</p>
                @else
                    <div class="saas-today-summary-list">
                        @foreach ($rows as $index => $row)
                            <div class="saas-today-summary-row" wire:key="today-summary-{{ $label }}-{{ $index }}">
                                <span class="saas-today-summary-index">#{{ $index + 1 }}</span>
                                <p class="saas-today-summary-name">{{ $row['name'] }}</p>
                                <a href="{{ $row['url'] }}" class="saas-today-summary-details">جزئیات</a>
                            </div>
                        @endforeach
                    </div>
                @endif
            </article>
        @endforeach
    </div>
</section>
