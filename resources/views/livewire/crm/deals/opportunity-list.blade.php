@php
    use App\Application\Crm\Deals\DealMoney;
@endphp

<div class="saas-page space-y-5">
    @include('livewire.crm.deals.partials.toolbar', [
        'activeView' => $activeView,
        'kanbanUrl' => $kanbanUrl,
        'listUrl' => $listUrl,
        'pipelinesUrl' => $pipelinesUrl,
        'pipelines' => $pipelines,
        'currentPipeline' => $currentPipeline,
        'canCreate' => $canCreate,
        'canManagePipelines' => $canManagePipelines,
        'showOwnerFilter' => $showOwnerFilter,
        'showFilters' => $showFilters,
        'datePreset' => $datePreset,
    ])

    <section class="saas-card space-y-4 p-4">
        @if ($showFilters)
            <div class="grid gap-3 lg:grid-cols-12 lg:items-end">
                <label class="block text-sm lg:col-span-4">
                    <span class="mb-1 block text-zinc-500">وضعیت</span>
                    <select wire:model.live="status" class="saas-input">
                        <option value="all">همه وضعیت‌ها</option>
                        @foreach ($statuses as $dealStatus)
                            <option value="{{ $dealStatus->value }}">{{ $dealStatus->label() }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block text-sm lg:col-span-4">
                    <span class="mb-1 block text-zinc-500">از تاریخ ایجاد</span>
                    <input wire:model.live="dateFrom" type="date" class="saas-input" aria-label="انتخاب تاریخ شروع">
                </label>

                <label class="block text-sm lg:col-span-4">
                    <span class="mb-1 block text-zinc-500">تا تاریخ ایجاد</span>
                    <input wire:model.live="dateTo" type="date" class="saas-input" aria-label="انتخاب تاریخ پایان">
                </label>
            </div>
        @endif

        @if ($deals->isEmpty())
            <x-saas.empty-state
                title="معامله‌ای پیدا نشد"
                description="با تغییر فیلترها یا افزودن معامله جدید، این فهرست پر می‌شود."
            >
                <a href="{{ $kanbanUrl }}?create=1" class="saas-btn-primary mt-6 text-sm" wire:navigate>افزودن معامله</a>
            </x-saas.empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="saas-table min-w-full">
                    <thead>
                        <tr>
                            <th>عنوان</th>
                            <th>کاریز</th>
                            <th>مرحله / وضعیت</th>
                            <th>مخاطب</th>
                            <th>مبلغ</th>
                            <th>تاریخ</th>
                            <th class="text-end">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($deals as $deal)
                            @php
                                $contact = $deal->primaryContact?->customer;
                                $contactLabel = $contact?->displayName()
                                    ?: ($contact?->phone_number ?: ($deal->company?->name ?: '—'));
                            @endphp
                            <tr wire:key="opportunity-{{ $deal->id }}">
                                <td>
                                    <a
                                        href="{{ route($portal.'.crm.deals.show', $deal) }}"
                                        class="font-medium text-zinc-900 hover:text-indigo-700 dark:text-white"
                                        wire:navigate
                                    >{{ $deal->title }}</a>
                                </td>
                                <td>{{ $deal->pipeline?->name ?: '—' }}</td>
                                <td>
                                    <div class="space-y-0.5">
                                        <p class="font-medium text-zinc-800 dark:text-zinc-100">{{ $deal->stage?->name ?: '—' }}</p>
                                        <p class="text-xs text-zinc-500">{{ $deal->status->label() }}</p>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap" dir="ltr">{{ $contactLabel }}</td>
                                <td class="whitespace-nowrap">{{ DealMoney::formatRials($deal->amount_rials) }}</td>
                                <td class="whitespace-nowrap">
                                    @if ($deal->last_activity_at || $deal->created_at)
                                        <span class="inline-flex items-center gap-1.5 text-zinc-600 dark:text-zinc-300">
                                            <svg class="h-3.5 w-3.5 shrink-0 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <rect x="3" y="5" width="18" height="16" rx="2" />
                                                <path stroke-linecap="round" d="M8 3v4M16 3v4M3 11h18" />
                                            </svg>
                                            {{ shamsi($deal->last_activity_at ?? $deal->created_at) }}
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        <a
                                            href="{{ route($portal.'.crm.deals.show', $deal) }}"
                                            class="saas-btn-secondary px-3 py-1.5 text-xs"
                                            wire:navigate
                                        >جزئیات</a>
                                        <button
                                            type="button"
                                            class="saas-btn border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs text-rose-700 hover:bg-rose-100 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-300 dark:hover:bg-rose-950/50"
                                            wire:click="archiveDeal({{ $deal->id }})"
                                            wire:confirm="این فرصت حذف شود؟"
                                        >حذف</button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div>{{ $deals->links() }}</div>
        @endif
    </section>
</div>
