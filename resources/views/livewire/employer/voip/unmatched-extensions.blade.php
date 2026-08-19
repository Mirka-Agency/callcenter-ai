<div class="saas-page space-y-6">
    <x-saas.page-header
        title="{{ __('ui.voip.unmatched_extensions_title') }}"
        description="{{ __('ui.voip.unmatched_extensions_page_hint') }}"
    />

    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-100">
            {{ session('status') }}
        </div>
    @endif

    <div class="saas-card space-y-4">
        @include('livewire.employer.voip.partials.unmatched-extensions-table')
    </div>
</div>
