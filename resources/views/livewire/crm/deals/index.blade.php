@php
    use App\Application\Crm\Deals\DealMoney;
    use App\Support\PersianNumber;
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
        'showOwnerFilter' => true,
        'isManager' => $isManager,
        'showFilters' => $showFilters,
        'datePreset' => $datePreset,
    ])

    @if ($pipelines->isEmpty())
        <x-saas.empty-state
            title="هنوز کاریزی ساخته نشده"
            description="مدیر سازمان می‌تواند اولین کاریز فروش را با مراحل نمونه بسازد."
        >
            @if ($canManagePipelines)
                <a href="{{ $pipelinesUrl }}" class="saas-btn-primary mt-6 text-sm" wire:navigate>مدیریت کاریزها</a>
            @endif
        </x-saas.empty-state>
    @else
        <div class="grid gap-3 rounded-xl border border-zinc-200 bg-zinc-50/80 px-4 py-3 text-sm dark:border-zinc-800 dark:bg-zinc-900/40 sm:grid-cols-2 xl:grid-cols-4">
            <div class="flex items-center justify-between gap-3 sm:block">
                <p class="text-zinc-500">معامله باز</p>
                <p class="mt-0.5 text-base font-semibold text-zinc-900 dark:text-white">{{ PersianNumber::format($summary['open_count']) }}</p>
            </div>
            <div class="flex items-center justify-between gap-3 sm:block">
                <p class="text-zinc-500">ارزش باز</p>
                <p class="mt-0.5 text-base font-semibold text-zinc-900 dark:text-white">{{ DealMoney::formatRials($summary['open_amount_rials']) }}</p>
            </div>
            <div class="flex items-center justify-between gap-3 sm:block">
                <p class="text-zinc-500">موفق</p>
                <p class="mt-0.5 text-base font-semibold text-emerald-700 dark:text-emerald-400">{{ PersianNumber::format($summary['won_count']) }}</p>
            </div>
            <div class="flex items-center justify-between gap-3 sm:block">
                <p class="text-zinc-500">ناموفق</p>
                <p class="mt-0.5 text-base font-semibold text-rose-700 dark:text-rose-400">{{ PersianNumber::format($summary['lost_count']) }}</p>
            </div>
        </div>

        @if ($suggestions->isNotEmpty())
            <section class="saas-card space-y-3 border-indigo-100 bg-indigo-50/40 p-4 dark:border-indigo-900/40 dark:bg-indigo-950/20" aria-labelledby="deal-suggestions-heading">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 id="deal-suggestions-heading" class="text-lg font-semibold text-indigo-950 dark:text-indigo-100">پیشنهادهای هوش مصنوعی</h2>
                        <p class="mt-1 text-sm text-indigo-900/70 dark:text-indigo-200/70">از تحلیل تماس ساخته شده‌اند؛ تا تأیید شما فرصت یا مرحله عوض نمی‌شود.</p>
                    </div>
                    <span class="rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-medium text-indigo-800 dark:bg-indigo-900/60 dark:text-indigo-200">
                        {{ PersianNumber::format($suggestions->count()) }} پیشنهاد در انتظار
                    </span>
                </div>
                @foreach ($suggestions as $suggestion)
                    <article class="rounded-xl border border-indigo-100 bg-white p-3 dark:border-indigo-900/50 dark:bg-zinc-950" wire:key="suggestion-{{ $suggestion->id }}">
                        <p class="font-medium text-zinc-900 dark:text-white">{{ $suggestion->suggestion_type->label() }}</p>
                        <p class="mt-1 text-sm text-zinc-600">{{ $suggestion->customer?->displayName() }} — {{ $suggestion->proposed_title ?: 'اتصال به فرصت موجود' }}</p>
                        @if (! empty($suggestion->payload['summary']))
                            <p class="mt-2 text-sm text-zinc-500">{{ \Illuminate\Support\Str::limit($suggestion->payload['summary'], 180) }}</p>
                        @endif
                        <div class="mt-3 flex flex-wrap gap-2">
                            @if ($suggestion->suggestion_type->value === 'create_deal')
                                <button type="button" class="saas-btn-primary text-sm" wire:click="acceptSuggestion({{ $suggestion->id }}, null)">ساخت فرصت</button>
                            @else
                                @foreach ((array) ($suggestion->payload['candidate_deal_ids'] ?? []) as $candidateId)
                                    <button type="button" class="saas-btn-secondary text-sm" wire:click="acceptSuggestion({{ $suggestion->id }}, {{ (int) $candidateId }})">اتصال به فرصت {{ $candidateId }}</button>
                                @endforeach
                            @endif
                            <button type="button" class="saas-btn-secondary text-sm" wire:click="dismissSuggestion({{ $suggestion->id }})">رد پیشنهاد</button>
                        </div>
                    </article>
                @endforeach
            </section>
        @endif

        @if ($status === 'open')
            <div class="flex gap-4 overflow-x-auto pb-2" aria-label="برد مراحل معاملات">
                @foreach ($stages as $stage)
                    @php
                        $columnDeals = $board->get((int) $stage->id, collect());
                        $columnCount = $columnDeals->count();
                    @endphp
                    <section
                        class="flex w-72 shrink-0 flex-col rounded-xl border border-zinc-200 bg-zinc-50/90 dark:border-zinc-800 dark:bg-zinc-900/40"
                        wire:key="column-{{ $stage->id }}"
                        x-data
                        x-on:dragover.prevent
                        x-on:drop.prevent="const id = event.dataTransfer.getData('text/plain'); if (id) { $wire.moveDeal(Number(id), {{ $stage->id }}) }"
                    >
                        <header class="flex items-center justify-between gap-2 border-b border-zinc-200/80 px-3 py-3 dark:border-zinc-800">
                            <div class="flex min-w-0 items-center gap-2">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $stage->color }}"></span>
                                <h2 class="truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ $stage->name }}</h2>
                            </div>
                            <span class="rounded-full bg-white px-2 py-0.5 text-xs font-medium text-zinc-600 ring-1 ring-zinc-200 dark:bg-zinc-950 dark:text-zinc-300 dark:ring-zinc-700">
                                {{ PersianNumber::format($columnCount) }}
                            </span>
                        </header>
                        <div class="max-h-[70vh] flex-1 space-y-2.5 overflow-y-auto p-3">
                            @forelse ($columnDeals as $deal)
                                @include('livewire.crm.deals.partials.card', ['deal' => $deal, 'stages' => $stages, 'portal' => $portal])
                            @empty
                                <div class="flex min-h-36 flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-zinc-300 px-3 py-6 text-center dark:border-zinc-700">
                                    <svg class="h-8 w-8 text-zinc-300 dark:text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h8M8 12h8M8 17h5" />
                                        <rect x="4" y="4" width="16" height="16" rx="2" />
                                    </svg>
                                    <p class="text-xs text-zinc-500">خالی — بکشید یا اضافه کنید</p>
                                </div>
                            @endforelse
                        </div>
                        @if ($canCreate && $stage->is_active)
                            <footer class="border-t border-zinc-200/80 px-3 py-2.5 dark:border-zinc-800">
                                <button
                                    type="button"
                                    wire:click="startCreateInStage({{ $stage->id }})"
                                    class="flex w-full items-center justify-center gap-1.5 rounded-lg py-1.5 text-sm text-zinc-500 transition hover:bg-white hover:text-zinc-800 dark:hover:bg-zinc-950 dark:hover:text-zinc-200"
                                >
                                    <span class="text-base leading-none" aria-hidden="true">+</span>
                                    <span>افزودن معامله جدید</span>
                                </button>
                            </footer>
                        @endif
                    </section>
                @endforeach
            </div>
        @else
            <div class="saas-card overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-zinc-500">
                        <tr class="border-b border-zinc-200 dark:border-zinc-800">
                            <th class="px-4 py-3 text-start"><button type="button" wire:click="sortBy('title')">عنوان</button></th>
                            <th class="px-4 py-3 text-start">شخص / شرکت</th>
                            <th class="px-4 py-3 text-start"><button type="button" wire:click="sortBy('amount')">مبلغ</button></th>
                            <th class="px-4 py-3 text-start"><button type="button" wire:click="sortBy('stage')">مرحله</button></th>
                            <th class="px-4 py-3 text-start"><button type="button" wire:click="sortBy('owner')">مسئول</button></th>
                            <th class="px-4 py-3 text-start">وضعیت</th>
                            <th class="px-4 py-3 text-start"><button type="button" wire:click="sortBy('activity')">آخرین فعالیت</button></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($deals as $deal)
                            <tr class="border-b border-zinc-100 dark:border-zinc-800" wire:key="row-{{ $deal->id }}">
                                <td class="px-4 py-3">
                                    <a href="{{ route($portal.'.crm.deals.show', $deal) }}" class="font-medium text-indigo-700" wire:navigate>{{ $deal->title }}</a>
                                </td>
                                <td class="px-4 py-3">{{ $deal->primaryContact?->customer?->displayName() ?: ($deal->company?->name ?: '—') }}</td>
                                <td class="px-4 py-3">{{ DealMoney::formatRials($deal->amount_rials) }}</td>
                                <td class="px-4 py-3">{{ $deal->stage?->name }}</td>
                                <td class="px-4 py-3">{{ $deal->owner?->full_name ?: 'بدون مسئول' }}</td>
                                <td class="px-4 py-3">{{ $deal->status->label() }}</td>
                                <td class="px-4 py-3">{{ $deal->last_activity_at ? shamsi($deal->last_activity_at) : '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-zinc-500">فرصتی با این فیلتر پیدا نشد.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-4">{{ $deals?->links() }}</div>
            </div>
        @endif
    @endif

    @if ($showCreate && $canCreate && $pipelines->isNotEmpty())
        <div
            class="fixed inset-0 z-50 flex items-end justify-center bg-zinc-950/40 p-4 sm:items-center"
            wire:click.self="closeCreateDealModal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="kariz-deal-modal-title"
        >
            <form
                wire:submit="createDeal"
                class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-950"
            >
                <div class="flex items-start justify-between gap-3 border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
                    <div class="min-w-0">
                        <h2 id="kariz-deal-modal-title" class="text-lg font-bold text-zinc-900 dark:text-white">فرصت جدید</h2>
                        <p class="mt-1 text-sm leading-6 text-zinc-500">
                            فرصت را در کاریز فعلی ثبت کنید. مرحله را می‌توانید همین‌جا انتخاب کنید.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                        wire:click="closeCreateDealModal"
                        aria-label="بستن"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-4 overflow-y-auto px-5 py-4">
                    <div class="grid gap-3 md:grid-cols-2">
                        <label class="block text-sm">
                            <span class="mb-1.5 block font-medium text-zinc-700 dark:text-zinc-300">عنوان</span>
                            <input wire:model="title" class="saas-input" required>
                            @error('title') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1.5 block font-medium text-zinc-700 dark:text-zinc-300">مبلغ (ریال)</span>
                            <input wire:model="amountRials" inputmode="numeric" class="saas-input" placeholder="مثلاً ۱۰۰۰۰۰۰">
                            <span class="mt-1 block text-xs text-zinc-500">هر ۱۰ ریال برابر ۱ تومان است.</span>
                            @error('amount_rials') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1.5 block font-medium text-zinc-700 dark:text-zinc-300">مرحله</span>
                            <select wire:model="stageId" class="saas-input">
                                @foreach ($stages as $stage)
                                    @if ($stage->is_active)
                                        <option value="{{ $stage->id }}">{{ $stage->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                            @error('stage') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1.5 block font-medium text-zinc-700 dark:text-zinc-300">تاریخ احتمالی بسته‌شدن</span>
                            <input wire:model="expectedCloseOn" type="date" class="saas-input">
                            @error('expected_close_on') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </label>
                        @if ($isManager)
                            <label class="block text-sm">
                                <span class="mb-1.5 block font-medium text-zinc-700 dark:text-zinc-300">کارشناس مسئول</span>
                                <select wire:model="ownerId" class="saas-input">
                                    <option value="">بدون مسئول</option>
                                    @foreach ($owners as $member)
                                        <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                                    @endforeach
                                </select>
                                @error('owner') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </label>
                        @endif
                        <label class="block text-sm">
                            <span class="mb-1.5 block font-medium text-zinc-700 dark:text-zinc-300">شخص مرتبط</span>
                            <input wire:model.live.debounce.300ms="customerQuery" class="saas-input" placeholder="نام یا شماره">
                            @if ($customerChoices->isNotEmpty())
                                <ul class="mt-2 space-y-1">
                                    @foreach ($customerChoices as $customer)
                                        <li>
                                            <button type="button" class="text-sm text-indigo-700" wire:click="chooseCustomer({{ $customer->id }})">{{ $customer->displayName() }}</button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            @error('customer') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </label>
                        <label class="block text-sm md:col-span-2">
                            <span class="mb-1.5 block font-medium text-zinc-700 dark:text-zinc-300">توضیحات</span>
                            <textarea wire:model="description" rows="3" class="saas-input"></textarea>
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3 border-t border-zinc-100 px-5 py-4 dark:border-zinc-800">
                    <button type="button" class="text-sm font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-300" wire:click="closeCreateDealModal">
                        انصراف
                    </button>
                    <button type="submit" class="saas-btn-primary text-sm" wire:loading.attr="disabled">
                        ثبت فرصت
                    </button>
                </div>
            </form>
        </div>
    @endif

    @if ($showCreatePipeline && $canManagePipelines)
        <div
            class="fixed inset-0 z-50 flex items-end justify-center bg-zinc-950/40 p-4 sm:items-center"
            wire:click.self="closeCreatePipelineModal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="kariz-create-modal-title"
        >
            <div class="w-full max-w-lg overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-950">
                <div class="flex items-start justify-between gap-3 border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
                    <div class="min-w-0">
                        <h2 id="kariz-create-modal-title" class="text-lg font-bold text-zinc-900 dark:text-white">کاریز جدید</h2>
                        <p class="mt-1 text-sm leading-6 text-zinc-500">
                            کاریز فروش یا پشتیبانی بسازید؛ مراحل پیش‌فرض ساخته می‌شوند.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                        wire:click="closeCreatePipelineModal"
                        aria-label="بستن"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-5 px-5 py-4">
                    <div>
                        <p class="mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">نوع</p>
                        <div class="grid grid-cols-2 gap-3" role="radiogroup" aria-label="نوع کاریز">
                            <button
                                type="button"
                                role="radio"
                                aria-checked="{{ $pipelineKind === 'sales' ? 'true' : 'false' }}"
                                wire:click="selectPipelineKind('sales')"
                                @class([
                                    'rounded-xl border px-4 py-3 text-start transition',
                                    'border-zinc-900 bg-zinc-50 dark:border-white dark:bg-zinc-900' => $pipelineKind === 'sales',
                                    'border-zinc-200 hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-500' => $pipelineKind !== 'sales',
                                ])
                            >
                                <span class="block text-sm font-semibold text-zinc-900 dark:text-white">فروش</span>
                                <span class="mt-1 block text-xs text-zinc-500">سرنخ تا موفق/ناموفق</span>
                            </button>
                            <button
                                type="button"
                                role="radio"
                                aria-checked="{{ $pipelineKind === 'support' ? 'true' : 'false' }}"
                                wire:click="selectPipelineKind('support')"
                                @class([
                                    'rounded-xl border px-4 py-3 text-start transition',
                                    'border-zinc-900 bg-zinc-50 dark:border-white dark:bg-zinc-900' => $pipelineKind === 'support',
                                    'border-zinc-200 hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-500' => $pipelineKind !== 'support',
                                ])
                            >
                                <span class="block text-sm font-semibold text-zinc-900 dark:text-white">پشتیبانی</span>
                                <span class="mt-1 block text-xs text-zinc-500">تیکت تا حل‌شده/بسته</span>
                            </button>
                        </div>
                    </div>

                    <label class="block text-sm">
                        <span class="mb-1.5 block font-medium text-zinc-700 dark:text-zinc-300">نام کاریز</span>
                        <input
                            wire:model="pipelineName"
                            type="text"
                            class="saas-input"
                            maxlength="120"
                            required
                        >
                        @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </label>
                </div>

                <div class="flex items-center justify-between gap-3 border-t border-zinc-100 px-5 py-4 dark:border-zinc-800">
                    <button type="button" class="text-sm font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-300" wire:click="closeCreatePipelineModal">
                        انصراف
                    </button>
                    <button type="button" class="saas-btn-primary text-sm" wire:click="createPipeline" wire:loading.attr="disabled">
                        ایجاد کاریز
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($showStagesModal && $canManagePipelines && $currentPipeline)
        <div
            class="fixed inset-0 z-50 flex items-end justify-center bg-zinc-950/40 p-4 sm:items-center"
            wire:click.self="closeStagesModal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="kariz-stages-modal-title"
        >
            <div class="w-full max-w-xl overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-950">
                <div class="flex items-start justify-between gap-3 border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
                    <div class="min-w-0">
                        <h2 id="kariz-stages-modal-title" class="text-lg font-bold text-zinc-900 dark:text-white">مدیریت مراحل کاریز</h2>
                        <p class="mt-1 text-sm leading-6 text-zinc-500">
                            مراحل باز کاریز را اضافه یا حذف کنید. موفق/ناموفق ستون کاریز نیستند و از منوی کارت ثبت می‌شوند.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                        wire:click="closeStagesModal"
                        aria-label="بستن"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-5 px-5 py-4">
                    <label class="block text-sm">
                        <span class="mb-1.5 block font-medium text-zinc-700 dark:text-zinc-300">نام مرحله جدید</span>
                        <input
                            wire:model="newStageName"
                            type="text"
                            class="saas-input"
                            placeholder="مثلاً مذاکره"
                            maxlength="80"
                            wire:keydown.enter.prevent="addStage"
                        >
                        @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </label>

                    <div>
                        <h3 class="mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">مراحل فعلی</h3>
                        <div class="space-y-2">
                            @forelse ($stages as $stage)
                                <div
                                    class="flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-900"
                                    wire:key="manage-stage-{{ $stage->id }}"
                                >
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $stage->color }}"></span>
                                    <input
                                        type="text"
                                        class="saas-input min-w-0 flex-1 border-zinc-200 py-1.5 text-sm"
                                        wire:model="stageNames.{{ $stage->id }}"
                                        wire:blur="saveStageName({{ $stage->id }})"
                                        maxlength="80"
                                        aria-label="نام مرحله {{ $stage->name }}"
                                    >
                                    <span class="min-w-6 text-center text-sm tabular-nums text-zinc-500">
                                        {{ PersianNumber::format($stageOpenCounts[$stage->id] ?? 0) }}
                                    </span>
                                    <button
                                        type="button"
                                        class="rounded-lg p-1.5 text-zinc-400 transition hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/40"
                                        wire:click="deleteManagedStage({{ $stage->id }})"
                                        wire:confirm="این مرحله حذف شود؟ اگر فرصتی داشته باشد به نزدیک‌ترین مرحله دیگر منتقل می‌شود."
                                        aria-label="حذف مرحله {{ $stage->name }}"
                                    >
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12m-9 0V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m2 0v12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V7h12Z" />
                                        </svg>
                                    </button>
                                </div>
                            @empty
                                <p class="rounded-xl border border-dashed border-zinc-200 px-3 py-6 text-center text-sm text-zinc-500 dark:border-zinc-700">
                                    هنوز مرحله‌ای برای این کاریز نیست.
                                </p>
                            @endforelse
                        </div>
                        @error('stage') <p class="mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror
                        @error('transfer') <p class="mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3 border-t border-zinc-100 px-5 py-4 dark:border-zinc-800">
                    <button type="button" class="text-sm font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-300" wire:click="closeStagesModal">
                        انصراف
                    </button>
                    <button type="button" class="saas-btn-primary text-sm" wire:click="addStage" wire:loading.attr="disabled">
                        افزودن مرحله
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
