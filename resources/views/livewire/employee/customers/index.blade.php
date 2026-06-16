<div class="saas-page space-y-8">
    <x-saas.page-header
        title="مشتریان"
        description="اطلاعات مشتری و تاریخچه تماس‌های مرتبط با کار شما."
    />

    <div class="saas-card">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="جستجو..." class="saas-input max-w-md">
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        @forelse ($customers as $customer)
            <x-saas.customer-card
                :customer="$customer"
                :href="route('employee.customers.show', $customer)"
                wire:key="customer-{{ $customer->id }}"
            />
        @empty
            <div class="col-span-full">
                <x-saas.empty-state title="هنوز مشتری ثبت نشده" description="مشتریان پس از تحلیل تماس‌ها اینجا ظاهر می‌شوند." />
            </div>
        @endforelse
    </div>

    {{ $customers->links() }}
</div>
