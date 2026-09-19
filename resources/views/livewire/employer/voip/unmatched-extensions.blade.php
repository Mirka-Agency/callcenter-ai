<div class="saas-page space-y-6">
    <x-saas.page-header
        data-tour="page-header"
        title="{{ __('ui.voip.unmatched_extensions_title') }}"
        description="{{ __('ui.voip.unmatched_extensions_page_hint') }}"
    >
        <x-slot:actions>
            <a href="{{ route('employer.employees.create') }}" class="saas-btn-secondary">
                {{ __('ui.voip.unmatched_add_employee') }}
            </a>
        </x-slot:actions>
    </x-saas.page-header>

    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-100">
            {{ session('status') }}
        </div>
    @endif

    @if ($unmatchedExtensions !== [])
        <div class="grid gap-4 sm:grid-cols-2" data-tour="unmatched-stats">
            <x-saas.stat-card
                :label="__('ui.voip.unmatched_stat_extensions')"
                :value="number_format(count($unmatchedExtensions))"
            />
            <x-saas.stat-card
                :label="__('ui.voip.unmatched_stat_calls')"
                :value="number_format($waitingCallCount)"
            />
        </div>

        <section class="saas-hero saas-hero--accent space-y-4" data-tour="unmatched-how">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('ui.voip.unmatched_how_title') }}</h2>
            <ol class="grid gap-4 sm:grid-cols-3">
                <li class="rounded-lg border border-indigo-100/80 bg-white/70 p-4 dark:border-indigo-900/40 dark:bg-zinc-900/60">
                    <p class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">۱</p>
                    <p class="mt-2 font-medium text-zinc-900 dark:text-white">{{ __('ui.voip.unmatched_how_1_title') }}</p>
                    <p class="mt-1 text-sm text-zinc-500">{{ __('ui.voip.unmatched_how_1_body') }}</p>
                </li>
                <li class="rounded-lg border border-indigo-100/80 bg-white/70 p-4 dark:border-indigo-900/40 dark:bg-zinc-900/60">
                    <p class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">۲</p>
                    <p class="mt-2 font-medium text-zinc-900 dark:text-white">{{ __('ui.voip.unmatched_how_2_title') }}</p>
                    <p class="mt-1 text-sm text-zinc-500">{{ __('ui.voip.unmatched_how_2_body') }}</p>
                </li>
                <li class="rounded-lg border border-indigo-100/80 bg-white/70 p-4 dark:border-indigo-900/40 dark:bg-zinc-900/60">
                    <p class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">۳</p>
                    <p class="mt-2 font-medium text-zinc-900 dark:text-white">{{ __('ui.voip.unmatched_how_3_title') }}</p>
                    <p class="mt-1 text-sm text-zinc-500">{{ __('ui.voip.unmatched_how_3_body') }}</p>
                </li>
            </ol>
        </section>
    @endif

    <div data-tour="unmatched-list">
        @include('livewire.employer.voip.partials.unmatched-extensions-table')
    </div>
</div>
