@php
    $contactCreateRoute = $portal === 'employee' ? 'employee.customers.contacts.create' : 'employer.customers.contacts.create';
    $companyCreateRoute = $portal === 'employee' ? 'employee.customers.companies.create' : 'employer.customers.companies.create';
    $reviewRoute = $portal === 'employee' ? 'employee.customers.reviews.index' : 'employer.customers.reviews.index';
@endphp

<div class="saas-page space-y-6" data-tour="customers-directory">
    <x-saas.page-header
        title="اشخاص و شرکت‌ها"
        description="مدیریت مخاطبان، اشخاص و شرکت‌های سازمان"
        class="crm-directory-header"
        data-tour="page-header"
    >
        <x-slot:actions>
            <div class="crm-directory-header-actions">
                @include('livewire.shared.customers.partials.directory-stats', [
                    'stats' => $stats,
                    'portal' => $portal,
                    'active' => 'contacts',
                ])
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <a href="{{ route($companyCreateRoute) }}" class="saas-btn border border-zinc-200 bg-white text-sm text-zinc-700 hover:bg-zinc-50" wire:navigate>شرکت جدید</a>
                    <a href="{{ route($contactCreateRoute) }}" class="saas-btn-primary text-sm" wire:navigate>شخص جدید</a>
                    <a href="{{ route($reviewRoute) }}" class="saas-btn border border-zinc-200 bg-white text-sm text-zinc-700 hover:bg-zinc-50" wire:navigate>نیازمند بررسی</a>
                    <x-saas.customer-list-export :portal="$portal" entity="contacts" :search="$search" :sort="$sort" />
                </div>
            </div>
        </x-slot:actions>
    </x-saas.page-header>

    @include('livewire.shared.customers.partials.directory-tabs', ['portal' => $portal, 'active' => 'contacts'])

    @include('livewire.shared.customers.partials.contacts-filters', [
        'portal' => $portal,
        'ownerChoices' => $ownerChoices,
        'lifecycleCounts' => $lifecycleCounts,
        'showOwnerPolicy' => $portal === 'employer',
    ])

    <section class="saas-card space-y-4 p-4 sm:p-5" data-tour="customers-directory-panel">
        @if ($contacts->isEmpty())
            <x-saas.empty-state
                title="{{ __('ui.empty.no_contacts.title') }}"
                description="{{ __('ui.empty.no_contacts.description') }}"
            >
                <a href="{{ route($contactCreateRoute) }}" class="saas-btn-primary mt-4 text-sm" wire:navigate>ثبت اولین شخص</a>
            </x-saas.empty-state>
        @else
            @include('livewire.shared.customers.partials.contacts-table', [
                'contacts' => $contacts,
                'portal' => $portal,
            ])
            @include('livewire.shared.customers.partials.directory-pagination', ['paginator' => $contacts])
        @endif
    </section>
</div>
