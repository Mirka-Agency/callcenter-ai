@php
    use App\Application\Crm\Deals\DealMoney;
    use App\Support\PersianNumber;
@endphp

<div class="saas-page space-y-5">
    <x-saas.page-header
        title="کاریز"
        description="مراحل کاریز را مدیریت کنید؛ فرصت‌های موفق و ناموفق در لیست جداگانه دیده می‌شوند."
    >
        <x-slot:actions>
            <div class="flex flex-wrap items-center justify-end gap-2">
                @if ($canManagePipelines && $currentPipeline)
                    <button
                        type="button"
                        class="saas-btn-secondary text-sm"
                        wire:click="deleteCurrentPipeline"
                        wire:confirm="این کاریز حذف شود؟ فقط وقتی فرصت نداشته باشد قابل حذف است."
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12m-9 0V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m2 0v12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V7h12Z" />
                        </svg>
                        حذف کاریز
                    </button>
                    <button type="button" class="saas-btn-secondary text-sm" wire:click="$set('showCreatePipeline', true)">
                        + کاریز جدید
                    </button>
                    <a
                        href="{{ route('employer.crm.pipelines.show', $currentPipeline) }}"
                        class="saas-btn-secondary text-sm"
                        wire:navigate
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h10M4 18h7" />
                        </svg>
                        مدیریت مراحل
                    </a>
                @elseif ($canManagePipelines)
                    <button type="button" class="saas-btn-secondary text-sm" wire:click="$set('showCreatePipeline', true)">
                        + کاریز جدید
                    </button>
                @endif

                @if ($canCreate && $pipelines->isNotEmpty())
                    <button type="button" class="saas-btn-primary text-sm" wire:click="$toggle('showCreate')">
                        + فرصت جدید
                    </button>
                @endif
            </div>
        </x-slot:actions>
    </x-saas.page-header>

    @if ($pipelines->isEmpty())
        <x-saas.empty-state
            title="هنوز کاریزی ساخته نشده"
            description="مدیر سازمان می‌تواند اولین کاریز فروش را با مراحل نمونه بسازد."
        >
            @if ($canManagePipelines)
                <button type="button" class="saas-btn-primary mt-6 text-sm" wire:click="$set('showCreatePipeline', true)">ساخت کاریز</button>
            @endif
        </x-saas.empty-state>
    @else
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap items-center gap-2" role="tablist" aria-label="کاریزها">
                @foreach ($pipelines as $pipeline)
                    @php $openCount = (int) ($pipelineOpenCounts[$pipeline->id] ?? 0); @endphp
                    <button
                        type="button"
                        role="tab"
                        aria-selected="{{ (string) $pipeline->id === $pipelineId ? 'true' : 'false' }}"
                        wire:click="selectPipeline('{{ $pipeline->id }}')"
                        @class([
                            'inline-flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-sm transition',
                            'border-indigo-200 bg-indigo-50 font-semibold text-indigo-800 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-200' => (string) $pipeline->id === $pipelineId,
                            'border-zinc-200 bg-white text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300' => (string) $pipeline->id !== $pipelineId,
                        ])
                    >
                        <span>{{ $pipeline->name }}</span>
                        <span @class([
                            'rounded-full px-1.5 py-0.5 text-xs',
                            'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/60 dark:text-indigo-200' => (string) $pipeline->id === $pipelineId,
                            'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' => (string) $pipeline->id !== $pipelineId,
                        ])>{{ PersianNumber::format($openCount) }}</span>
                    </button>
                @endforeach
            </div>

            <label class="relative block w-full max-w-sm lg:ms-auto">
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
        </div>

        <div class="flex flex-wrap items-center gap-2" role="tablist" aria-label="وضعیت فرصت‌ها">
            @foreach ([
                'open' => ['label' => 'کاریز', 'count' => $summary['open_count'], 'icon' => null],
                'won' => ['label' => 'موفق', 'count' => $summary['won_count'], 'icon' => 'won'],
                'lost' => ['label' => 'ناموفق', 'count' => $summary['lost_count'], 'icon' => 'lost'],
            ] as $statusKey => $tab)
                <button
                    type="button"
                    role="tab"
                    aria-selected="{{ $status === $statusKey ? 'true' : 'false' }}"
                    wire:click="selectStatus('{{ $statusKey }}')"
                    @class([
                        'inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm transition',
                        'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' => $status === $statusKey,
                        'border-zinc-200 bg-white text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300' => $status !== $statusKey,
                    ])
                >
                    @if ($tab['icon'] === 'won')
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" />
                        </svg>
                    @elseif ($tab['icon'] === 'lost')
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18" />
                        </svg>
                    @endif
                    <span>{{ $tab['label'] }}</span>
                    <span @class([
                        'rounded-full px-1.5 py-0.5 text-xs',
                        'bg-white/20 text-white dark:bg-zinc-900/10 dark:text-zinc-900' => $status === $statusKey,
                        'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' => $status !== $statusKey,
                    ])>{{ PersianNumber::format($tab['count']) }}</span>
                </button>
            @endforeach

            @if ($isManager)
                <label class="ms-auto inline-flex items-center gap-2 text-sm text-zinc-500">
                    <span class="sr-only">مسئول</span>
                    <select wire:model.live="owner" class="saas-input py-1.5 text-sm">
                        <option value="all">همه مسئولان</option>
                        <option value="mine">فرصت‌های من</option>
                        <option value="unassigned">بدون مسئول</option>
                    </select>
                </label>
            @else
                <label class="ms-auto inline-flex items-center gap-2 text-sm text-zinc-500">
                    <span class="sr-only">مسئول</span>
                    <select wire:model.live="owner" class="saas-input py-1.5 text-sm">
                        <option value="all">فرصت‌های من</option>
                        <option value="mine">فقط من</option>
                    </select>
                </label>
            @endif
        </div>

        <div class="grid gap-3 rounded-xl border border-zinc-200 bg-zinc-50/80 px-4 py-3 text-sm dark:border-zinc-800 dark:bg-zinc-900/40 sm:grid-cols-2 xl:grid-cols-4">
            <div class="flex items-center justify-between gap-3 sm:block">
                <p class="text-zinc-500">فرصت باز</p>
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

        @if ($showCreatePipeline && $canManagePipelines)
            <form wire:submit="createPipeline" class="saas-card space-y-4 p-4">
                <h2 class="text-lg font-semibold">کاریز جدید</h2>
                <div class="grid gap-3 md:grid-cols-2">
                    <label class="block text-sm">
                        <span class="mb-1 block text-zinc-600">نام کاریز</span>
                        <input wire:model="pipelineName" class="saas-input" required placeholder="مثلاً فروش">
                        @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block text-zinc-600">توضیح (اختیاری)</span>
                        <input wire:model="pipelineDescription" class="saas-input" placeholder="مثلاً فرصت‌های سازمانی">
                    </label>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="saas-btn-primary text-sm" wire:loading.attr="disabled">ساخت کاریز</button>
                    <button type="button" class="saas-btn-secondary text-sm" wire:click="$set('showCreatePipeline', false)">انصراف</button>
                </div>
            </form>
        @endif

        @if ($showCreate)
            <form wire:submit="createDeal" class="saas-card space-y-4 p-4">
                <h2 class="text-lg font-semibold">فرصت جدید</h2>
                <div class="grid gap-3 md:grid-cols-2">
                    <label class="block text-sm">
                        <span class="mb-1 block text-zinc-600">عنوان</span>
                        <input wire:model="title" class="saas-input" required>
                        @error('title') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block text-zinc-600">مبلغ (ریال)</span>
                        <input wire:model="amountRials" inputmode="numeric" class="saas-input" placeholder="مثلاً ۱۰۰۰۰۰۰">
                        <span class="mt-1 block text-xs text-zinc-500">هر ۱۰ ریال برابر ۱ تومان است. مبلغ اعشاری پذیرفته نمی‌شود.</span>
                        @error('amount_rials') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block text-zinc-600">مرحله</span>
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
                        <span class="mb-1 block text-zinc-600">تاریخ احتمالی بسته‌شدن</span>
                        <input wire:model="expectedCloseOn" type="date" class="saas-input">
                        @error('expected_close_on') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </label>
                    @if ($isManager)
                        <label class="block text-sm">
                            <span class="mb-1 block text-zinc-600">کارشناس مسئول</span>
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
                        <span class="mb-1 block text-zinc-600">شخص مرتبط</span>
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
                        <span class="mb-1 block text-zinc-600">توضیحات</span>
                        <textarea wire:model="description" rows="3" class="saas-input"></textarea>
                    </label>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="saas-btn-primary text-sm" wire:loading.attr="disabled">ثبت فرصت</button>
                    <button type="button" class="saas-btn-secondary text-sm" wire:click="$set('showCreate', false)">انصراف</button>
                </div>
            </form>
        @endif

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
            <div class="flex gap-4 overflow-x-auto pb-2" aria-label="برد مراحل کاریز">
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
                                    <span>فرصت در این مرحله</span>
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
</div>
