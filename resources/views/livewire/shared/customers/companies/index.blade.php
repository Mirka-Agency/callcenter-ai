@php
    $companyShowRoute = $portal === 'employee' ? 'employee.customers.companies.show' : 'employer.customers.companies.show';
    $companyCreateRoute = $portal === 'employee' ? 'employee.customers.companies.create' : 'employer.customers.companies.create';
@endphp

<div class="saas-page space-y-6">
    @include('livewire.shared.customers.partials.section-nav', ['portal' => $portal, 'active' => 'companies'])

    <x-saas.page-header
        data-tour="page-header"
        title="سازمان‌ها"
        description="شرکت‌ها و سازمان‌های مشتری — هر سازمان می‌تواند چند مخاطب داشته باشد."
    >
        <x-slot:actions>
            <a href="{{ route($companyCreateRoute) }}" class="saas-btn-primary text-sm" wire:navigate>سازمان جدید</a>
        </x-slot:actions>
    </x-saas.page-header>

    <div class="saas-card p-4" data-tour="customers-companies-search">
        <input
            wire:model.live.debounce.300ms="search"
            type="search"
            placeholder="جستجو در نام، صنعت، تلفن یا ایمیل سازمان..."
            class="saas-input w-full max-w-xl"
        >
    </div>

    <div class="grid gap-5 lg:grid-cols-2" data-tour="customers-companies-grid">
        @forelse ($companies as $company)
            <x-saas.customer-company-card
                :company="$company"
                :href="route($companyShowRoute, $company)"
                wire:key="company-{{ $company->id }}"
            />
        @empty
            <div class="col-span-full">
                <x-saas.empty-state
                    title="{{ __('ui.empty.no_companies.title') }}"
                    description="{{ __('ui.empty.no_companies.description') }}"
                >
                    <a href="{{ route($companyCreateRoute) }}" class="saas-btn-primary mt-4 text-sm" wire:navigate>ثبت اولین سازمان</a>
                </x-saas.empty-state>
            </div>
        @endforelse
    </div>

    {{ $companies->links() }}
</div>
