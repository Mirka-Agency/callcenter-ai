<div class="saas-page space-y-6" data-tour="customers-directory">
    <x-saas.page-header
        title="اشخاص و شرکت‌ها"
        description="مدیریت مخاطبان، اشخاص و شرکت‌های سازمان"
        class="crm-directory-header"
        data-tour="page-header"
    >
        <x-slot:actions>
            @include('livewire.shared.customers.partials.directory-header-actions', [
                'stats' => $stats,
                'portal' => $portal,
                'active' => 'companies',
                'exportEntity' => 'companies',
                'search' => $search,
                'sort' => $sort,
            ])
        </x-slot:actions>
    </x-saas.page-header>

    @include('livewire.shared.customers.partials.directory-tabs', ['portal' => $portal, 'active' => 'companies'])

    <section class="saas-card space-y-4 p-4 sm:p-5" data-tour="customers-directory-panel">
        <div class="crm-directory-toolbar" data-tour="customers-companies-search">
            <div class="crm-directory-filters">
                <label class="block min-w-40 text-sm">
                    <span class="sr-only">مرتب‌سازی</span>
                    <select wire:model.live="sort" class="saas-input py-2 text-sm">
                        @foreach (\App\Support\CustomerListSort::cases() as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <label class="relative block min-w-0 flex-1 sm:max-w-sm sm:ms-auto">
                <span class="sr-only">جستجو</span>
                <span class="pointer-events-none absolute inset-y-0 start-3 flex items-center text-zinc-400">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <circle cx="11" cy="11" r="7" />
                        <path stroke-linecap="round" d="m20 20-3.5-3.5" />
                    </svg>
                </span>
                <input
                    wire:model.live.debounce.300ms="search"
                    type="search"
                    class="saas-input ps-10"
                    placeholder="جستجو در نام، صنعت، تلفن یا ایمیل شرکت..."
                >
            </label>
        </div>

        @if ($companies->isEmpty())
            <x-saas.empty-state
                title="{{ __('ui.empty.no_companies.title') }}"
                description="{{ __('ui.empty.no_companies.description') }}"
            >
                <button type="button" class="saas-btn-brand mt-4 text-sm" wire:click="openCreateDialog('company')">ثبت اولین شرکت</button>
            </x-saas.empty-state>
        @else
            @include('livewire.shared.customers.partials.companies-table', [
                'companies' => $companies,
                'portal' => $portal,
            ])
            @include('livewire.shared.customers.partials.directory-pagination', ['paginator' => $companies])
        @endif
    </section>

    @include('livewire.shared.customers.partials.create-dialog')
</div>
