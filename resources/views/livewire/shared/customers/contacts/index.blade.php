@php
    $contactShowRoute = $portal === 'employee' ? 'employee.customers.show' : 'employer.customers.show';
@endphp

<div class="saas-page space-y-6">
    @include('livewire.shared.customers.partials.section-nav', ['portal' => $portal, 'active' => 'contacts'])

    <x-saas.page-header
        title="مخاطبین"
        description="افراد و مخاطبان سازمان‌ها — با یا بدون سازمان. پروفایل از تحلیل تماس‌ها ساخته می‌شود."
        data-tour="page-header"
    />

    <div class="saas-card p-4" data-tour="customers-contacts-search">
        <input
            wire:model.live.debounce.300ms="search"
            type="search"
            placeholder="جستجو در نام، سازمان، شماره یا ایمیل..."
            class="saas-input w-full max-w-xl"
        >
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
                />
            </div>
        @endforelse
    </div>

    {{ $contacts->links() }}
</div>
