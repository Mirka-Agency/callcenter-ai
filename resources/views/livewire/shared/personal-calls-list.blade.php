@php
    use App\Support\AnalysisCallPresenter;

    $personalCalls = $personalCalls ?? collect();
    $personalCallTotal = $personalCallTotal ?? $personalCalls->count();
    $showRoute = $showRoute ?? null;
@endphp

<section class="saas-card overflow-hidden p-0" data-tour="personal-calls">
    <div class="border-b border-amber-200/80 bg-amber-50/60 px-4 py-4 dark:border-amber-500/20 dark:bg-amber-950/20 sm:px-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h2 class="text-lg font-semibold text-amber-950 dark:text-amber-100">تماس‌های شخصی</h2>
                <p class="mt-1 text-sm leading-relaxed text-amber-900/80 dark:text-amber-200/80">
                    تماس‌هایی که با تلفن شرکت انجام شده‌اند ولی موضوعشان به کار سازمان مربوط نیست.
                    این تماس‌ها در امتیاز عملکرد، کوچینگ و پرونده مشتریان محاسبه نمی‌شوند.
                </p>
            </div>
            <span class="rounded-md bg-white/80 px-2.5 py-1 text-sm font-semibold tabular-nums text-amber-900 dark:bg-amber-900/40 dark:text-amber-100">
                {{ number_format($personalCallTotal) }}
            </span>
        </div>
    </div>

    @if ($personalCalls->isEmpty())
        <p class="px-4 py-6 text-sm text-zinc-500 sm:px-6">در این بازه تماس شخصی شناسایی نشده است.</p>
    @else
        <div class="divide-y divide-zinc-200/80 dark:divide-zinc-800">
            @foreach ($personalCalls as $analysis)
                @php
                    $occurredAt = AnalysisCallPresenter::callOccurredAt($analysis);
                    $direction = AnalysisCallPresenter::direction($analysis);
                    $number = AnalysisCallPresenter::counterpartyNumber($analysis);
                    $href = $showRoute ? route($showRoute, $analysis) : null;
                @endphp
                @if ($href)
                    <a
                        href="{{ $href }}"
                        wire:navigate
                        data-personal-call="{{ $analysis->id }}"
                        class="block px-4 py-4 transition hover:bg-amber-50/40 dark:hover:bg-amber-950/10 sm:px-6"
                    >
                @else
                    <div data-personal-call="{{ $analysis->id }}" class="px-4 py-4 sm:px-6">
                @endif
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="saas-badge bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200">شخصی</span>
                                @if ($occurredAt)
                                    <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ shamsi($occurredAt, 'datetime') }}</span>
                                @else
                                    <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ shamsi($analysis->analyzed_at, 'datetime') }}</span>
                                @endif
                                @if ($direction)
                                    <span class="text-xs text-zinc-500">{{ $direction->label() }}</span>
                                @endif
                                @if ($number)
                                    <span class="text-xs tabular-nums text-zinc-500" dir="ltr">{{ $number }}</span>
                                @endif
                                <span class="text-xs tabular-nums text-zinc-500">{{ AnalysisCallPresenter::durationLabel($analysis) }}</span>
                            </div>
                            @if (filled($analysis->personal_reason))
                                <p class="mt-2 text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $analysis->personal_reason }}</p>
                            @endif
                            @if (filled($analysis->summary) && $analysis->summary !== $analysis->personal_reason)
                                <p class="mt-1 line-clamp-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $analysis->summary }}</p>
                            @endif
                        </div>
                    </div>
                @if ($href)
                    </a>
                @else
                    </div>
                @endif
            @endforeach
        </div>
        @if ($personalCallTotal > $personalCalls->count())
            <p class="border-t border-zinc-200/80 px-4 py-3 text-xs text-zinc-500 dark:border-zinc-800 sm:px-6">
                {{ number_format($personalCalls->count()) }} تماس اخیر از {{ number_format($personalCallTotal) }} تماس شخصی این بازه
            </p>
        @endif
    @endif
</section>
