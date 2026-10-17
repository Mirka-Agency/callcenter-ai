@php
    $activeView = $activeView ?? 'kanban';
    $datePreset = $datePreset ?? 'today_and_past';
    $showFilters = $showFilters ?? false;
    $canCreate = $canCreate ?? false;
    $canManagePipelines = $canManagePipelines ?? false;
    $pipelinesUrl = $pipelinesUrl ?? null;
    $showOwnerFilter = $showOwnerFilter ?? false;
    $isManager = $isManager ?? false;
    $statuses = $statuses ?? [];
    $currentPipeline = $currentPipeline ?? null;
    $pipelines = $pipelines ?? collect();
    $searchValue = trim((string) ($search ?? ''));
    $ownerValue = (string) ($owner ?? 'all');
    $statusValue = (string) ($status ?? 'all');
    $dateFromValue = trim((string) ($dateFrom ?? ''));
    $dateToValue = trim((string) ($dateTo ?? ''));
    $ownerOptions = $isManager
        ? ['all' => 'همه مسئولان', 'mine' => 'فرصت‌های من', 'unassigned' => 'بدون مسئول']
        : ['all' => 'فرصت‌های من', 'mine' => 'فقط من'];
    $statusOptions = ['all' => 'همه'];
    foreach ($statuses as $dealStatus) {
        $statusOptions[$dealStatus->value] = $dealStatus->label();
    }
    $kanbanStatuses = [
        'open' => ['label' => 'باز', 'icon' => null, 'count' => isset($summary) ? ($summary['open_count'] ?? null) : null],
        'won' => ['label' => 'موفق', 'icon' => 'won', 'count' => isset($summary) ? ($summary['won_count'] ?? null) : null],
        'lost' => ['label' => 'ناموفق', 'icon' => 'lost', 'count' => isset($summary) ? ($summary['lost_count'] ?? null) : null],
    ];
    $activeFilterCount = ($searchValue !== '' ? 1 : 0)
        + ($showOwnerFilter && $ownerValue !== 'all' ? 1 : 0)
        + ($activeView === 'list' && $statusValue !== 'all' ? 1 : 0)
        + ($activeView !== 'list' && $statusValue !== 'open' ? 1 : 0)
        + ($activeView === 'list' && $dateFromValue !== '' ? 1 : 0)
        + ($activeView === 'list' && $dateToValue !== '' ? 1 : 0);
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

        @if ($canManagePipelines && ! empty($pipelinesUrl))
            <a
                href="{{ $pipelinesUrl }}"
                wire:navigate
                class="crm-deals-icon-btn"
                title="همه کاریزها"
                aria-label="همه کاریزها"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <circle cx="12" cy="12" r="3" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z" />
                </svg>
            </a>
        @endif

        <button
            type="button"
            @class(['crm-deals-filter-btn', 'is-active' => $showFilters || $activeFilterCount > 0])
            wire:click="toggleFilters"
            aria-haspopup="dialog"
            aria-expanded="{{ $showFilters ? 'true' : 'false' }}"
            aria-controls="crm-deals-filters-dialog"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M7 12h10M10 18h4" />
            </svg>
            <span>فیلترها</span>
            @if ($activeFilterCount > 0)
                <span class="crm-deals-filter-btn__count">{{ \App\Support\PersianNumber::format($activeFilterCount) }}</span>
            @endif
        </button>

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
    <div
        id="crm-deals-filters-dialog"
        class="crm-deals-filter-dialog"
        wire:click.self="closeFilters"
        wire:keydown.escape.window="closeFilters"
        role="dialog"
        aria-modal="true"
        aria-labelledby="crm-deals-filters-title"
        aria-describedby="crm-deals-filters-hint"
        data-testid="crm-deals-filters"
    >
        <div class="crm-deals-filter-dialog__panel">
            <div class="crm-deals-filter-dialog__header">
                <div class="crm-deals-filter-dialog__heading">
                    <span class="crm-deals-filter-dialog__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M7 12h10M10 18h4" />
                        </svg>
                    </span>
                    <div>
                        <h2 id="crm-deals-filters-title">فیلتر معاملات</h2>
                        <p id="crm-deals-filters-hint">فهرست با هر تغییر، همان لحظه به‌روز می‌شود.</p>
                    </div>
                </div>
                <button type="button" class="crm-deals-filter-dialog__close" wire:click="closeFilters" aria-label="بستن">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18" />
                    </svg>
                </button>
            </div>

            <div class="crm-deals-filter-dialog__body">
                @if ($activeView === 'list')
                    <fieldset class="crm-deals-filter-group">
                        <legend class="crm-deals-filter-label">وضعیت</legend>
                        <div class="crm-deals-filter-segments is-{{ count($statusOptions) }}" role="radiogroup" aria-label="وضعیت">
                            @foreach ($statusOptions as $value => $label)
                                <button
                                    type="button"
                                    role="radio"
                                    aria-checked="{{ $statusValue === $value ? 'true' : 'false' }}"
                                    wire:click="$set('status', '{{ $value }}')"
                                    @class(['crm-deals-filter-segment', 'is-active' => $statusValue === $value])
                                >{{ $label }}</button>
                            @endforeach
                        </div>
                    </fieldset>
                @else
                    <fieldset class="crm-deals-filter-group">
                        <legend class="crm-deals-filter-label">وضعیت</legend>
                        <div class="crm-deals-filter-segments is-3" role="radiogroup" aria-label="وضعیت معاملات">
                            @foreach ($kanbanStatuses as $value => $tab)
                                <button
                                    type="button"
                                    role="radio"
                                    aria-checked="{{ $statusValue === $value ? 'true' : 'false' }}"
                                    wire:click="selectStatus('{{ $value }}')"
                                    @class(['crm-deals-filter-segment', 'has-meta', 'is-active' => $statusValue === $value])
                                >
                                    @if ($tab['icon'] === 'won')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" />
                                        </svg>
                                    @elseif ($tab['icon'] === 'lost')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18" />
                                        </svg>
                                    @endif
                                    <span>{{ $tab['label'] }}</span>
                                    @if ($tab['count'] !== null)
                                        <span class="crm-deals-filter-segment__count">{{ \App\Support\PersianNumber::format($tab['count']) }}</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </fieldset>
                @endif

                <label class="crm-deals-filter-search">
                    <span class="crm-deals-filter-label">جستجو</span>
                    <span class="crm-deals-filter-search__control">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <circle cx="11" cy="11" r="7" />
                            <path stroke-linecap="round" d="m20 20-3.5-3.5" />
                        </svg>
                        <input
                            wire:model.live.debounce.300ms="search"
                            type="search"
                            placeholder="عنوان یا نام مخاطب"
                            autofocus
                        >
                        @if ($searchValue !== '')
                            <button type="button" wire:click="$set('search', '')" aria-label="پاک کردن جستجو">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18" />
                                </svg>
                            </button>
                        @endif
                    </span>
                </label>

                @if ($showOwnerFilter)
                    <fieldset class="crm-deals-filter-group">
                        <legend class="crm-deals-filter-label">مسئول</legend>
                        <div class="crm-deals-filter-segments is-{{ count($ownerOptions) }}" role="radiogroup" aria-label="مسئول">
                            @foreach ($ownerOptions as $value => $label)
                                <button
                                    type="button"
                                    role="radio"
                                    aria-checked="{{ $ownerValue === $value ? 'true' : 'false' }}"
                                    wire:click="$set('owner', '{{ $value }}')"
                                    @class(['crm-deals-filter-segment', 'is-active' => $ownerValue === $value])
                                >{{ $label }}</button>
                            @endforeach
                        </div>
                    </fieldset>
                @endif

                @if ($activeView === 'list')
                    <fieldset class="crm-deals-filter-group">
                        <legend class="crm-deals-filter-label">تاریخ ایجاد</legend>
                        <div class="crm-deals-filter-dates">
                            <label class="crm-deals-filter-date">
                                <span>از</span>
                                <input wire:model.live="dateFrom" type="date" aria-label="انتخاب تاریخ شروع">
                            </label>
                            <label class="crm-deals-filter-date">
                                <span>تا</span>
                                <input wire:model.live="dateTo" type="date" aria-label="انتخاب تاریخ پایان">
                            </label>
                        </div>
                    </fieldset>
                @endif
            </div>

            <div class="crm-deals-filter-dialog__footer">
                <button
                    type="button"
                    class="crm-deals-filter-clear"
                    wire:click="clearFilters"
                    @disabled($activeFilterCount === 0)
                >
                    پاک کردن
                </button>
                <button type="button" class="crm-deals-filter-apply" wire:click="closeFilters">
                    نمایش نتایج
                </button>
            </div>
        </div>
    </div>
@endif
