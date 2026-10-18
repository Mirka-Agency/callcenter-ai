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
                'active' => 'contacts',
                'exportEntity' => 'contacts',
                'search' => $search,
                'sort' => $sort,
            ])
        </x-slot:actions>
    </x-saas.page-header>

    @include('livewire.shared.customers.partials.directory-tabs', ['portal' => $portal, 'active' => 'contacts'])

    @include('livewire.shared.customers.partials.contacts-filters', [
        'portal' => $portal,
        'ownerChoices' => $ownerChoices,
        'lifecycleCounts' => $lifecycleCounts,
    ])

    <section class="saas-card space-y-4 p-4 sm:p-5" data-tour="customers-directory-panel">
        @if ($contacts->isEmpty())
            <x-saas.empty-state
                title="{{ __('ui.empty.no_contacts.title') }}"
                description="{{ __('ui.empty.no_contacts.description') }}"
            >
                <button type="button" class="saas-btn-brand mt-4 text-sm" wire:click="openCreateDialog">ثبت اولین شخص</button>
            </x-saas.empty-state>
        @else
            @include('livewire.shared.customers.partials.contacts-table', [
                'contacts' => $contacts,
                'portal' => $portal,
            ])
            @include('livewire.shared.customers.partials.directory-pagination', ['paginator' => $contacts])
        @endif
    </section>

    @include('livewire.shared.customers.partials.create-dialog')
</div>
