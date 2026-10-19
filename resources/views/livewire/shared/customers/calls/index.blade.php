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
                'active' => null,
                'exportEntity' => 'contacts',
                'exportSearch' => '',
                'exportSort' => 'last_contact',
            ])
        </x-slot:actions>
    </x-saas.page-header>

    @include('livewire.shared.customers.partials.directory-tabs', ['portal' => $portal, 'active' => 'calls'])

    <section class="saas-card space-y-4 p-4 sm:p-5" data-tour="customers-directory-panel">
        <div class="crm-directory-toolbar" data-tour="customers-calls-search">
            <div class="crm-directory-filters">
                <label class="block min-w-40 text-sm">
                    <span class="sr-only">جهت تماس</span>
                    <select wire:model.live="direction" class="saas-input py-2 text-sm">
                        <option value="all">همه جهت‌ها</option>
                        <option value="inbound">ورودی</option>
                        <option value="outbound">خروجی</option>
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
                    placeholder="جستجو در شخص، شرکت یا شماره تماس..."
                >
            </label>
        </div>

        @if ($calls->isEmpty())
            <x-saas.empty-state
                title="تماسی با این فیلتر پیدا نشد"
                description="تماس‌های متصل به اشخاص اینجا نمایش داده می‌شوند."
            />
        @else
            @include('livewire.shared.customers.partials.calls-table', [
                'calls' => $calls,
                'portal' => $portal,
            ])
            @include('livewire.shared.customers.partials.directory-pagination', ['paginator' => $calls])
        @endif
    </section>

    @include('livewire.shared.customers.partials.create-dialog')
</div>
