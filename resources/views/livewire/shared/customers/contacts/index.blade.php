@php
    $contactShowRoute = $portal === 'employee' ? 'employee.customers.show' : 'employer.customers.show';
    $contactCreateRoute = $portal === 'employee' ? 'employee.customers.contacts.create' : 'employer.customers.contacts.create';
@endphp

<div class="saas-page space-y-6">
    <x-saas.page-header
        title="اشخاص"
        description="افراد و اشخاص شرکت‌ها — با یا بدون شرکت. پروفایل از تحلیل تماس‌ها ساخته می‌شود."
        data-tour="page-header"
    >
        <x-slot:actions>
            <a href="{{ route($contactCreateRoute) }}" class="saas-btn-primary text-sm" wire:navigate>شخص جدید</a>
        </x-slot:actions>
    </x-saas.page-header>

    <div class="saas-card p-4" data-tour="customers-contacts-search">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <input
                wire:model.live.debounce.300ms="search"
                type="search"
                placeholder="جستجو در نام، شرکت، شماره یا ایمیل..."
                class="saas-input w-full max-w-xl flex-1"
            >
            @include('livewire.shared.customers.partials.sort-select')
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3" data-tour="customers-contacts-grid">
        @forelse ($contacts as $customer)
            <x-saas.customer-card
                :customer="$customer"
                :href="route($contactShowRoute, $customer)"
                wire:key="contact-{{ $customer->id }}"
            />
        @empty
            <div class="col-span-full">
                <x-saas.empty-state
                    title="{{ __('ui.empty.no_contacts.title') }}"
                    description="{{ __('ui.empty.no_contacts.description') }}"
                >
                    <a href="{{ route($contactCreateRoute) }}" class="saas-btn-primary mt-4 text-sm" wire:navigate>ثبت اولین شخص</a>
                </x-saas.empty-state>
            </div>
        @endforelse
    </div>

    {{ $contacts->links() }}
</div>
