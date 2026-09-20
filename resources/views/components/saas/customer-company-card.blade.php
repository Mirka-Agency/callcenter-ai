@props([
    'company',
    'href' => null,
])

@php
    use App\Support\AnalysisInsightPresenter;
    use App\Support\CustomerCompanyPresenter;
@endphp

@php
    $cardClass = 'group relative block overflow-hidden rounded-2xl border border-zinc-200/80 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-indigo-600';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $cardClass]) }} wire:navigate>
@else
    <div {{ $attributes->merge(['class' => $cardClass]) }}>
@endif
    <div class="pointer-events-none absolute inset-x-0 top-0 h-1 bg-gradient-to-l from-indigo-500 via-violet-500 to-sky-500 opacity-0 transition group-hover:opacity-100"></div>

    <div class="flex items-start gap-4">
        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 text-lg font-bold text-white shadow-md shadow-indigo-500/25">
            {{ mb_substr($company->displayName(), 0, 1) }}
        </div>

        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div class="min-w-0">
                    <h3 class="truncate text-lg font-semibold text-zinc-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400">
                        {{ $company->displayName() }}
                    </h3>
                    <p class="mt-0.5 truncate text-sm text-zinc-500">{{ CustomerCompanyPresenter::subtitle($company) }}</p>
                </div>

                @if ($company->latest_lead_score)
                    <x-saas.score-ring :score="$company->latest_lead_score" size="sm" label="لید" class="shrink-0" />
                @endif
            </div>

            <div class="mt-3 flex flex-wrap gap-2">
                @if ($company->industry)
                    <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300">{{ $company->industry }}</span>
                @endif
                @if ($company->latest_lead_level)
                    <span @class(['rounded-full px-2.5 py-0.5 text-xs font-medium', CustomerCompanyPresenter::leadBadgeClass($company->latest_lead_level)])>
                        لید {{ AnalysisInsightPresenter::leadLevelLabel($company->latest_lead_level) }}
                    </span>
                @endif
                @if ($company->conversation_trend)
                    <span @class(['rounded-full px-2.5 py-0.5 text-xs font-medium', CustomerCompanyPresenter::trendBadgeClass($company->conversation_trend)])>
                        {{ CustomerCompanyPresenter::trendLabel($company->conversation_trend) }}
                    </span>
                @endif
            </div>
        </div>
    </div>

    <p class="mt-4 border-t border-zinc-100 pt-4 text-sm text-zinc-600 dark:border-zinc-800 dark:text-zinc-400">
        {{ CustomerCompanyPresenter::metaLine($company) }}
    </p>

    @php
        $contactPreview = CustomerCompanyPresenter::contactPreview($company);
    @endphp
    @if ($contactPreview !== '')
        <p class="mt-4 truncate text-sm text-zinc-600 dark:text-zinc-400">
            {{ $contactPreview }}
        </p>
    @endif

    @if ($company->recommended_next_action)
        <p class="mt-3 truncate text-xs text-zinc-500">
            <span class="font-medium text-zinc-400">اقدام بعدی:</span>
            {{ $company->recommended_next_action }}
        </p>
    @endif
@if ($href)
    </a>
@else
    </div>
@endif
