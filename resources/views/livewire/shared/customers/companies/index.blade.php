@php
    $companyShowRoute = $portal === 'employee' ? 'employee.customers.companies.show' : 'employer.customers.companies.show';
    $companyCreateRoute = $portal === 'employee' ? 'employee.customers.companies.create' : 'employer.customers.companies.create';
@endphp

<div class="saas-page space-y-6">
    <x-saas.page-header
        data-tour="page-header"
        title="شرکت‌ها"
        description="شرکت‌های مشتری — هر شرکت می‌تواند چند شخص داشته باشد."
    >
        <x-slot:actions>
            <a href="{{ route($companyCreateRoute) }}" class="saas-btn-primary text-sm" wire:navigate>شرکت جدید</a>
        </x-slot:actions>
    </x-saas.page-header>

    <div class="saas-card p-4" data-tour="customers-companies-search">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <input
                wire:model.live.debounce.300ms="search"
                type="search"
                placeholder="جستجو در نام، صنعت، تلفن یا ایمیل شرکت..."
                class="saas-input w-full max-w-xl flex-1"
            >
            @include('livewire.shared.customers.partials.sort-select')
        </div>
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
                    <a href="{{ route($companyCreateRoute) }}" class="saas-btn-primary mt-4 text-sm" wire:navigate>ثبت اولین شرکت</a>
                </x-saas.empty-state>
            </div>
        @endforelse
    </div>

    {{ $companies->links() }}
</div>
