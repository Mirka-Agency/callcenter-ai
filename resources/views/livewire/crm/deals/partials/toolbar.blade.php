@php
    $activeView = $activeView ?? 'kanban';
    $datePreset = $datePreset ?? 'today_and_past';
    $showFilters = $showFilters ?? false;
    $canCreate = $canCreate ?? false;
    $canManagePipelines = $canManagePipelines ?? false;
    $pipelinesUrl = $pipelinesUrl ?? null;
    $showOwnerFilter = $showOwnerFilter ?? false;
    $isManager = $isManager ?? false;
    $currentPipeline = $currentPipeline ?? null;
    $pipelines = $pipelines ?? collect();
    $datePresetLabels = [
        'today_and_past' => 'امروز و تاریخ گذشته',
        'today' => 'فقط امروز',
        'upcoming' => 'تاریخ آینده',
        'all' => 'همه تاریخ‌ها',
    ];
@endphp

<div class="crm-deals-toolbar" data-testid="crm-deals-toolbar">
    <div class="crm-deals-toolbar__start" role="group" aria-label="نمای معاملات">
        <a
            href="{{ $kanbanUrl }}"
            wire:navigate
            @class(['crm-deals-view-btn', 'is-active' => $activeView === 'kanban'])
            @if ($activeView === 'kanban') aria-current="page" @endif
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 5h4v14H4V5Zm6 0h4v9h-4V5Zm6 0h4v6h-4V5Z" />
            </svg>
            <span>کاریز</span>
        </a>
        <a
            href="{{ $listUrl }}"
            wire:navigate
            @class(['crm-deals-view-btn', 'is-active' => $activeView === 'list'])
            @if ($activeView === 'list') aria-current="page" @endif
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12M8 12h12M8 17h12M4 7h.01M4 12h.01M4 17h.01" />
            </svg>
            <span>لیست</span>
        </a>
    </div>

    <div class="crm-deals-toolbar__middle">
        <label class="crm-deals-select">
            <span class="sr-only">انتخاب کاریز</span>
            <svg class="crm-deals-select__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 5h6v6H4V5Zm10 0h6v6h-6V5ZM4 13h6v6H4v-6Zm10 2h6v4h-6v-4Z" />
            </svg>
            <select
                wire:model.live="pipelineId"
                class="crm-deals-select__control"
                @disabled($pipelines->isEmpty())
            >
                @if ($activeView === 'list')
                    <option value="">همه کاریزها</option>
                @endif
                @forelse ($pipelines as $pipeline)
                    <option value="{{ $pipeline->id }}">{{ $pipeline->name }}</option>
                @empty
                    @if ($activeView !== 'list')
                        <option value="">کاریزی نیست</option>
                    @endif
                @endforelse
            </select>
            <svg class="crm-deals-select__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
            </svg>
        </label>

        <label class="crm-deals-select">
            <span class="sr-only">بازه تاریخ</span>
            <svg class="crm-deals-select__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7 3v3m10-3v3M4 9h16M6 5h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z" />
            </svg>
            <select wire:model.live="datePreset" class="crm-deals-select__control">
                @foreach ($datePresetLabels as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <svg class="crm-deals-select__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
            </svg>
        </label>
    </div>

    <div class="crm-deals-toolbar__end">
        <button
            type="button"
            class="crm-deals-icon-btn"
            wire:click="refreshBoard"
            title="بازنشانی"
            aria-label="بازنشانی فهرست معاملات"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v6h6M20 20v-6h-6M5 15a7 7 0 0 0 12.2 3M19 9A7 7 0 0 0 6.8 6" />
            </svg>
        </button>

        <button
            type="button"
            @class(['crm-deals-filter-btn', 'is-active' => $showFilters])
            wire:click="toggleFilters"
            aria-expanded="{{ $showFilters ? 'true' : 'false' }}"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M7 12h10M10 18h4" />
            </svg>
            <span>فیلترها</span>
        </button>

        @if ($canManagePipelines && ! empty($pipelinesUrl))
            <a href="{{ $pipelinesUrl }}" wire:navigate class="crm-deals-pipelines-btn" title="مدیریت همه کاریزها">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h10" />
                </svg>
                <span>همه کاریزها</span>
            </a>
        @endif

        @if ($canCreate && $pipelines->isNotEmpty())
            @if ($activeView === 'list')
                <a href="{{ $kanbanUrl }}?create=1" wire:navigate class="crm-deals-add-btn">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                    </svg>
                    <span>افزودن معامله</span>
                </a>
            @else
                <button type="button" class="crm-deals-add-btn" wire:click="openCreateDealModal">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                    </svg>
                    <span>افزودن معامله</span>
                </button>
            @endif
        @endif
    </div>
</div>

@if ($showFilters)
    <div class="crm-deals-filters" data-testid="crm-deals-filters">
        <label class="relative block min-w-[14rem] flex-1">
            <span class="sr-only">جستجو</span>
            <span class="pointer-events-none absolute inset-y-0 start-3 flex items-center text-zinc-400">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <circle cx="11" cy="11" r="7" />
                    <path stroke-linecap="round" d="m20 20-3.5-3.5" />
                </svg>
            </span>
            <input
                wire:model.live.debounce.300ms="search"
                type="search"
                class="saas-input ps-10"
                placeholder="جستجو عنوان، مخاطب…"
            >
        </label>

        @if ($showOwnerFilter)
            <label class="block min-w-[10rem] text-sm">
                <span class="sr-only">مسئول</span>
                <select wire:model.live="owner" class="saas-input py-2.5 text-sm">
                    @if ($isManager)
                        <option value="all">همه مسئولان</option>
                        <option value="mine">فرصت‌های من</option>
                        <option value="unassigned">بدون مسئول</option>
                    @else
                        <option value="all">فرصت‌های من</option>
                        <option value="mine">فقط من</option>
                    @endif
                </select>
            </label>
        @endif

        @if ($canManagePipelines && ! empty($pipelinesUrl))
            <a href="{{ $pipelinesUrl }}" wire:navigate class="saas-btn-secondary ms-auto text-sm">
                مدیریت کاریزها
            </a>
        @endif
    </div>
@endif
