<div class="saas-page space-y-8">
    <x-saas.page-header
        data-tour="page-header"
        title="مشتریان"
        description="پایگاه هوش مشتری — ساخته‌شده خودکار از تحلیل تماس‌ها."
    />

    <div class="saas-card" data-tour="customers-search">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="جستجو بر اساس نام، شرکت یا شماره..." class="saas-input max-w-md">
    </div>

    <div class="grid gap-4 lg:grid-cols-2" data-tour="customers-grid">
        @forelse ($customers as $customer)
            <x-saas.customer-card
                :customer="$customer"
                :href="route('employer.customers.show', $customer)"
                wire:key="customer-{{ $customer->id }}"
            />
        @empty
            <div class="col-span-full">
                <x-saas.empty-state title="هنوز مشتری ثبت نشده" description="پس از تحلیل تماس‌ها، مشتریان به‌صورت خودکار اینجا نمایش داده می‌شوند." />
            </div>
        @endforelse
    </div>

    {{ $customers->links() }}
</div>
