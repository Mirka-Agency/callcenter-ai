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

        <label class="block min-w-44 text-sm text-zinc-600">
            <span class="mb-1 block">مرحله</span>
            <select wire:model.live="stageFilter" class="saas-input">
                @foreach ($lifecycleCounts as $key => $count)
                    <option value="{{ $key }}">{{ $stageLabels[$key] ?? $key }} ({{ JalaliDate::persianDigits($count) }})</option>
                @endforeach
            </select>
        </label>

        @include('livewire.shared.customers.partials.sort-select')
    </div>
</div>
