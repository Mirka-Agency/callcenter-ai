@php
    use App\Support\AnalysisCallPresenter;
@endphp

<div
    id="analysis-list"
    class="saas-card saas-analysis-list-panel overflow-hidden p-0"
    data-tour="analysis-list"
>
    <div class="saas-list-toolbar flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200/80 px-4 py-4 dark:border-zinc-800 sm:px-6">
        <div class="min-w-0">
            <h2 class="text-lg font-semibold">لیست تحلیل مکالمات</h2>
            <p class="mt-1 text-sm text-zinc-500">
                {{ number_format($analyses->total()) }} نتیجه
                @if ($filter->hasActiveFilters())
                    · فیلتر فعال
                @endif
                <span class="hidden sm:inline">· برای جزئیات روی هر ردیف کلیک کنید</span>
            </p>
        </div>
        <input
            wire:model.live.debounce.300ms="search"
            type="search"
            placeholder="جستجو در خلاصه، مشتری یا کارشناس..."
            class="saas-input w-full text-sm sm:max-w-xs"
        >
    </div>

    <div class="min-h-[12rem]">
        @if ($analyses->isEmpty())
            <div class="p-8">
                @if ($filter->hasActiveFilters())
                    <x-saas.empty-state
                        title="{{ __('ui.empty.no_results_filter.title') }}"
                        description="{{ __('ui.empty.no_results_filter.description') }}"
                    >
                        <button type="button" wire:click="clearFilters" class="saas-btn-primary mt-4">@lang('ui.cta.clear_filters')</button>
                    </x-saas.empty-state>
                @else
                    <x-saas.empty-state
                        title="{{ __('ui.empty.no_analyses.title') }}"
                        description="{{ __('ui.empty.no_analyses.description') }}"
                    />
                @endif
            </div>
        @else
            <div class="saas-analysis-list-scroll overflow-x-auto lg:overflow-x-auto">
                <div class="saas-analysis-list-table min-w-[66rem] lg:min-w-[66rem]">
                    <div class="saas-analysis-list-header saas-analysis-list-grid">
                        <div>
                            <button type="button" wire:click="sortByColumn('call_at')" class="inline-flex items-center gap-1 transition hover:text-zinc-900 dark:hover:text-white">
                                تاریخ تماس <x-saas.sort-icon :active="$sortBy === 'call_at'" :dir="$sortDir" />
                            </button>
                        </div>
                        <div>
                            <button type="button" wire:click="sortByColumn('analyzed_at')" class="inline-flex items-center gap-1 transition hover:text-zinc-900 dark:hover:text-white">
                                تاریخ تحلیل <x-saas.sort-icon :active="$sortBy === 'analyzed_at'" :dir="$sortDir" />
                            </button>
                        </div>
                        <div>
                            <button type="button" wire:click="sortByColumn('agent')" class="inline-flex items-center gap-1 transition hover:text-zinc-900 dark:hover:text-white">
                                کارشناس <x-saas.sort-icon :active="$sortBy === 'agent'" :dir="$sortDir" />
                            </button>
                        </div>
                        <div>خلاصه</div>
                        <div>
                            <button type="button" wire:click="sortByColumn('duration')" class="inline-flex items-center gap-1 transition hover:text-zinc-900 dark:hover:text-white">
                                مدت <x-saas.sort-icon :active="$sortBy === 'duration'" :dir="$sortDir" />
                            </button>
                        </div>
                        <div>
                            <button type="button" wire:click="sortByColumn('status')" class="inline-flex items-center gap-1 transition hover:text-zinc-900 dark:hover:text-white">
                                وضعیت <x-saas.sort-icon :active="$sortBy === 'status'" :dir="$sortDir" />
                            </button>
                        </div>
                        <div>جهت</div>
                        <div class="text-end">
                            <button type="button" wire:click="sortByColumn('score')" class="inline-flex items-center gap-1 transition hover:text-zinc-900 dark:hover:text-white">
                                امتیاز <x-saas.sort-icon :active="$sortBy === 'score'" :dir="$sortDir" />
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2 p-3">
                        @foreach ($analyses as $analysis)
                            @php
                                $status = AnalysisCallPresenter::status($analysis);
                                $direction = AnalysisCallPresenter::direction($analysis);
                                $callOccurredAt = AnalysisCallPresenter::callOccurredAt($analysis);
                            @endphp
                            <div
                                wire:key="analysis-{{ $analysis->id }}"
                                data-row-href="{{ route('employer.intelligence.show', $analysis) }}"
                                role="link"
                                tabindex="0"
                                aria-label="مشاهده جزئیات تحلیل {{ shamsi($analysis->analyzed_at) }}"
                                class="saas-analysis-row saas-analysis-list-grid group"
                            >
                                <div class="min-w-0 whitespace-nowrap">
                                    @if ($callOccurredAt)
                                        <p class="font-medium text-zinc-900 dark:text-white">{{ shamsi($callOccurredAt) }}</p>
                                        <p class="text-xs text-zinc-500">{{ shamsi($callOccurredAt, 'time') }}</p>
                                    @else
                                        <p class="font-medium text-zinc-400">—</p>
                                    @endif
                                </div>

                                <div class="min-w-0 whitespace-nowrap">
                                    <p class="font-medium text-zinc-900 dark:text-white">{{ shamsi($analysis->analyzed_at) }}</p>
                                    <p class="text-xs text-zinc-500">{{ shamsi($analysis->analyzed_at, 'time') }}</p>
                                </div>

                                <div class="min-w-0 whitespace-nowrap">
                                    @if ($analysis->employee)
                                        <button
                                            type="button"
                                            wire:click.stop="filterByAgent({{ $analysis->organization_user_id }})"
                                            data-row-ignore
                                            class="relative z-10 rounded-lg p-1 text-start transition hover:bg-white/90 hover:shadow-sm dark:hover:bg-zinc-800/90"
                                            title="فیلتر بر اساس این کارشناس"
                                        >
                                            <x-saas.user-cell
                                                :employee="$analysis->employee"
                                                :subtitle="$analysis->employee->department"
                                                avatar-size="xs"
                                            />
                                        </button>
                                    @else
                                        <div class="space-y-0.5">
                                            <span class="text-zinc-400">بدون اختصاص</span>
                                            @php
                                                $unassignedExtension = $analysis->callLog?->raw_payload['resolved_extension']
                                                    ?? $analysis->callLog?->raw_payload['extension']
                                                    ?? $analysis->call?->receiver_number;
                                            @endphp
                                            @if ($analysis->callLog?->source_number || $unassignedExtension)
                                                <p class="text-xs tabular-nums text-zinc-500" dir="ltr">
                                                    {{ $analysis->callLog?->source_number ?: '—' }}
                                                    →
                                                    {{ $unassignedExtension ?: ($analysis->callLog?->destination_number ?: '—') }}
                                                </p>
                                            @endif
                                        </div>
                                    @endif
                                </div>

                                <div class="min-w-0">
                                    <p class="line-clamp-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">{{ $analysis->summary }}</p>
                                    <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                        <p class="text-xs text-zinc-400">{{ $analysis->source?->label() ?? 'VoIP' }}</p>
                                        @if ($analysis->needs_attention)
                                            <span class="rounded-md bg-amber-50 px-1.5 py-0.5 text-[11px] font-medium text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">نیازمند توجه</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="whitespace-nowrap tabular-nums text-sm text-zinc-600 dark:text-zinc-400">
                                    {{ AnalysisCallPresenter::durationLabel($analysis) }}
                                </div>

                                <div class="whitespace-nowrap">
                                    @if ($status)
                                        <span @class(['saas-badge', AnalysisCallPresenter::statusBadgeClass($status)])>{{ $status->label() }}</span>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </div>

                                <div class="whitespace-nowrap">
                                    @if ($direction)
                                        <span class="saas-badge bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">{{ $direction->label() }}</span>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </div>

                                <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                    <span @class([
                                        'inline-flex h-9 min-w-9 items-center justify-center rounded-full px-2 text-sm font-bold tabular-nums transition-all duration-200',
                                        'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400' => ! $analysis->isEvaluable(),
                                        'bg-emerald-50 text-emerald-700 group-hover:bg-emerald-100 group-hover:shadow-sm dark:bg-emerald-950/40 dark:text-emerald-300 dark:group-hover:bg-emerald-950/60' => $analysis->isEvaluable() && $analysis->score >= 85,
                                        'bg-amber-50 text-amber-700 group-hover:bg-amber-100 group-hover:shadow-sm dark:bg-amber-950/40 dark:text-amber-300 dark:group-hover:bg-amber-950/60' => $analysis->isEvaluable() && $analysis->score >= 70 && $analysis->score < 85,
                                        'bg-red-50 text-red-700 group-hover:bg-red-100 group-hover:shadow-sm dark:bg-red-950/40 dark:text-red-300 dark:group-hover:bg-red-950/60' => $analysis->isEvaluable() && $analysis->score < 70,
                                    ]) title="{{ $analysis->isEvaluable() ? '' : __('ui.intelligence.not_evaluable') }}">{{ $analysis->isEvaluable() ? $analysis->score : '—' }}</span>
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-500/0 text-indigo-500 transition-all duration-200 group-hover:translate-x-0.5 group-hover:bg-indigo-500/10">
                                        <svg class="h-4 w-4 opacity-0 transition-opacity duration-200 group-hover:opacity-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                                        </svg>
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="border-t border-zinc-200/80 px-6 py-4 dark:border-zinc-800">
                {{ $analyses->links() }}
            </div>
        @endif
    </div>
</div>
