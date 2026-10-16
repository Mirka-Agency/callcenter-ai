@php
    use App\Domain\Customer\CustomerLifecycleStage;
    use App\Support\JalaliDate;

    $stageLabels = ['all' => 'همه مراحل', 'unknown' => CustomerLifecycleStage::unknownLabel()];
    foreach (CustomerLifecycleStage::cases() as $stage) {
        $stageLabels[$stage->value] = $stage->label();
    }
@endphp

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
                    'border-indigo-600 bg-indigo-50 text-indigo-800 dark:border-indigo-400 dark:bg-indigo-950/40 dark:text-indigo-200' => $stageFilter === $key,
                    'border-zinc-200 text-zinc-600 hover:border-zinc-300 dark:border-zinc-700 dark:text-zinc-300' => $stageFilter !== $key,
                ])
            >
                {{ $stageLabels[$key] ?? $key }}
                <span class="ms-1 tabular-nums">{{ JalaliDate::persianDigits($count) }}</span>
            </button>
        @endforeach
    </div>

    @if ($portal === 'employer' && isset($showOwnerPolicy) && $showOwnerPolicy)
        <div class="mt-4 flex flex-col gap-3 border-t border-zinc-100 pt-4 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-800">
            <label class="flex items-start gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                <input wire:model="assignFromCall" type="checkbox" class="mt-1 rounded border-zinc-300 text-indigo-600">
                <span>اگر شخص از تماس تازه ساخته شود و کارشناس همان تماس عضو فعال سازمان باشد، او مسئول پیش‌فرض شود. مشتریان قبلی تغییر نمی‌کنند.</span>
            </label>
            <button type="button" wire:click="saveOwnerPolicy" class="saas-btn-secondary shrink-0 text-sm">ذخیره سیاست</button>
        </div>
    @endif
</div>
