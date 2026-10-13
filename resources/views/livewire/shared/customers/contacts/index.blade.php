@php
    use App\Domain\Customer\CustomerLifecycleStage;

    $contactShowRoute = $portal === 'employee' ? 'employee.customers.show' : 'employer.customers.show';
    $contactCreateRoute = $portal === 'employee' ? 'employee.customers.contacts.create' : 'employer.customers.contacts.create';
    $stageLabels = ['all' => 'همه مراحل', 'unknown' => CustomerLifecycleStage::unknownLabel()];
    foreach (CustomerLifecycleStage::cases() as $stage) {
        $stageLabels[$stage->value] = $stage->label();
    }
@endphp

<div class="saas-page space-y-6">
    <x-saas.page-header
        title="اشخاص"
        description="افراد و اشخاص شرکت‌ها — با یا بدون شرکت. پروفایل از تحلیل تماس‌ها ساخته می‌شود."
        data-tour="page-header"
    >
        <x-slot:actions>
            <a href="{{ route($contactCreateRoute) }}" class="saas-btn-primary text-sm" wire:navigate>شخص جدید</a>
            <x-saas.customer-list-export :portal="$portal" entity="contacts" :search="$search" :sort="$sort" />
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
            <label class="block min-w-44 text-sm text-zinc-600">
                <span class="mb-1 block">مسئول</span>
                <select wire:model.live="ownerFilter" class="saas-input">
                    <option value="all">همه</option>
                    <option value="mine">مشتریان من</option>
                    <option value="unassigned">بدون مسئول</option>
                    <option value="specific">مسئول مشخص</option>
                </select>
            </label>
            @if ($ownerFilter === 'specific')
                <label class="block min-w-44 text-sm text-zinc-600">
                    <span class="mb-1 block">انتخاب مسئول</span>
                    <select wire:model.live="specificOwnerId" class="saas-input">
                        <option value="">انتخاب کنید</option>
                        @foreach ($ownerChoices as $member)
                            <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            @include('livewire.shared.customers.partials.sort-select')
        </div>
        <div class="mt-4 flex flex-wrap gap-2" role="group" aria-label="فیلتر مرحله چرخه عمر">
            @foreach ($lifecycleCounts as $key => $count)
                <button
                    type="button"
                    wire:click="$set('stageFilter', '{{ $key }}')"
                    @class([
                        'rounded-full border px-3 py-1 text-xs',
                        'border-indigo-600 bg-indigo-50 text-indigo-800' => $stageFilter === $key,
                        'border-zinc-200 text-zinc-600 hover:border-zinc-300' => $stageFilter !== $key,
                    ])
                >
                    {{ $stageLabels[$key] ?? $key }}
                    <span class="ms-1 tabular-nums">{{ $count }}</span>
                </button>
            @endforeach
        </div>
        @if ($portal === 'employer')
            <div class="mt-4 flex flex-col gap-3 border-t border-zinc-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                <label class="flex items-start gap-2 text-sm text-zinc-700">
                    <input wire:model="assignFromCall" type="checkbox" class="mt-1 rounded border-zinc-300 text-indigo-600">
                    <span>اگر شخص از تماس تازه ساخته شود و کارشناس همان تماس عضو فعال سازمان باشد، او مسئول پیش‌فرض شود. مشتریان قبلی تغییر نمی‌کنند.</span>
                </label>
                <button type="button" wire:click="saveOwnerPolicy" class="saas-btn-secondary shrink-0 text-sm">ذخیره سیاست</button>
            </div>
        @endif
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

    {{ $contacts->links('livewire.shared.customers.partials.contacts-pagination') }}
</div>
