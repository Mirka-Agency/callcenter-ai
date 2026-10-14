@php
    use App\Application\Crm\Deals\DealMoney;
    use App\Domain\Crm\Deals\DealStatus;
    use App\Support\PersianNumber;
@endphp

<div class="saas-page space-y-6">
    <nav class="text-sm text-zinc-500" aria-label="مسیر">
        <span>CRM</span>
        <span class="mx-2">/</span>
        <span class="text-zinc-800 dark:text-zinc-200">معاملات</span>
    </nav>

    <x-saas.page-header title="معاملات" description="فرصت‌های فروش همین سازمان. جابه‌جایی مرحله از سرور ثبت می‌شود.">
        <x-slot:actions>
            @if ($canCreate && $pipelines->isNotEmpty())
                <button type="button" class="saas-btn-primary text-sm" wire:click="$toggle('showCreate')">معامله جدید</button>
            @endif
            <div class="flex rounded-lg border border-zinc-200 p-1 dark:border-zinc-700" role="group" aria-label="نوع نمایش">
                <button type="button" wire:click="$set('viewMode', 'kanban')" @class(['rounded-md px-3 py-1 text-sm', 'bg-indigo-50 text-indigo-800' => $viewMode === 'kanban', 'text-zinc-600' => $viewMode !== 'kanban'])>کانبان</button>
                <button type="button" wire:click="$set('viewMode', 'table')" @class(['rounded-md px-3 py-1 text-sm', 'bg-indigo-50 text-indigo-800' => $viewMode === 'table', 'text-zinc-600' => $viewMode !== 'table'])>جدول</button>
            </div>
        </x-slot:actions>
    </x-saas.page-header>

    @if ($pipelines->isEmpty())
        <x-saas.empty-state title="پایپ‌لاین فروش هنوز ساخته نشده" description="مدیر سازمان می‌تواند از بخش پایپ‌لاین‌های فروش، یک پایپ‌لاین با مراحل نمونه بسازد." />
    @else
        <div class="saas-card space-y-4 p-4">
            <div class="grid gap-3 lg:grid-cols-4">
                <label class="block text-sm text-zinc-600">
                    <span class="mb-1 block">جستجو</span>
                    <input wire:model.live.debounce.300ms="search" type="search" class="saas-input" placeholder="عنوان، شخص یا شرکت">
                </label>
                <label class="block text-sm text-zinc-600">
                    <span class="mb-1 block">پایپ‌لاین</span>
                    <select wire:model.live="pipelineId" class="saas-input">
                        @foreach ($pipelines as $pipeline)
                            <option value="{{ $pipeline->id }}">{{ $pipeline->name }}{{ $pipeline->is_active ? '' : ' (غیرفعال)' }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm text-zinc-600">
                    <span class="mb-1 block">وضعیت</span>
                    <select wire:model.live="status" class="saas-input">
                        <option value="open">باز</option>
                        <option value="stale">متوقف / بدون فعالیت</option>
                        <option value="won">موفق</option>
                        <option value="lost">ناموفق</option>
                        <option value="all">همه به‌جز بایگانی</option>
                        <option value="archived">بایگانی</option>
                    </select>
                </label>
                <label class="block text-sm text-zinc-600">
                    <span class="mb-1 block">مسئول</span>
                    <select wire:model.live="owner" class="saas-input">
                        <option value="all">همه</option>
                        <option value="mine">معاملات من</option>
                        @if ($isManager)
                            <option value="unassigned">بدون مسئول</option>
                        @endif
                    </select>
                </label>
            </div>
        </div>

        @if ($showCreate)
            <form wire:submit="createDeal" class="saas-card space-y-4 p-4">
                <h2 class="text-lg font-semibold">معامله جدید</h2>
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
                    <button type="submit" class="saas-btn-primary text-sm" wire:loading.attr="disabled">ثبت معامله</button>
                    <button type="button" class="saas-btn-secondary text-sm" wire:click="$set('showCreate', false)">انصراف</button>
                </div>
            </form>
        @endif

        @if ($suggestions->isNotEmpty())
            <section class="saas-card space-y-3 p-4" aria-labelledby="deal-suggestions-heading">
                <h2 id="deal-suggestions-heading" class="text-lg font-semibold">پیشنهادهای هوش مصنوعی</h2>
                <p class="text-sm text-zinc-500">این موارد از تحلیل تماس ساخته شده‌اند و تا تأیید شما معامله یا مرحله را عوض نمی‌کنند.</p>
                @foreach ($suggestions as $suggestion)
                    <article class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800" wire:key="suggestion-{{ $suggestion->id }}">
                        <p class="font-medium">{{ $suggestion->suggestion_type->label() }}</p>
                        <p class="mt-1 text-sm text-zinc-600">{{ $suggestion->customer?->displayName() }} — {{ $suggestion->proposed_title ?: 'اتصال به معامله موجود' }}</p>
                        @if (! empty($suggestion->payload['summary']))
                            <p class="mt-2 text-sm text-zinc-500">{{ \Illuminate\Support\Str::limit($suggestion->payload['summary'], 180) }}</p>
                        @endif
                        <div class="mt-3 flex flex-wrap gap-2">
                            @if ($suggestion->suggestion_type->value === 'create_deal')
                                <button type="button" class="saas-btn-primary text-sm" wire:click="acceptSuggestion({{ $suggestion->id }}, null)">ساخت معامله</button>
                            @else
                                @foreach ((array) ($suggestion->payload['candidate_deal_ids'] ?? []) as $candidateId)
                                    <button type="button" class="saas-btn-secondary text-sm" wire:click="acceptSuggestion({{ $suggestion->id }}, {{ (int) $candidateId }})">اتصال به معامله {{ $candidateId }}</button>
                                @endforeach
                            @endif
                            <button type="button" class="saas-btn-secondary text-sm" wire:click="dismissSuggestion({{ $suggestion->id }})">رد پیشنهاد</button>
                        </div>
                    </article>
                @endforeach
            </section>
        @endif

        @if ($viewMode === 'kanban')
            @if ($status !== 'open')
                <p class="text-sm text-zinc-500">نمای کانبان فقط معاملات باز را نشان می‌دهد. برای وضعیت‌های دیگر از جدول استفاده کنید.</p>
            @endif
            <div class="flex gap-4 overflow-x-auto pb-2" aria-label="برد مراحل فروش">
                @foreach ($stages as $stage)
                    <section
                        class="w-72 shrink-0 rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-900/40"
                        wire:key="column-{{ $stage->id }}"
                        x-data
                        x-on:dragover.prevent
                        x-on:drop.prevent="const id = event.dataTransfer.getData('text/plain'); if (id) { $wire.moveDeal(Number(id), {{ $stage->id }}) }"
                    >
                        <header class="mb-3 flex items-center justify-between gap-2">
                            <h2 class="truncate text-sm font-semibold">{{ $stage->name }}</h2>
                            <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $stage->color }}"></span>
                        </header>
                        <div class="space-y-3">
                            @forelse ($board->get($stage->id, collect()) as $deal)
                                @include('livewire.crm.deals.partials.card', ['deal' => $deal, 'stages' => $stages, 'portal' => $portal])
                            @empty
                                <p class="text-xs text-zinc-500">معامله‌ای در این مرحله نیست.</p>
                            @endforelse
                        </div>
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
                                <td colspan="7" class="px-4 py-8 text-center text-zinc-500">معامله‌ای با این فیلتر پیدا نشد.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-4">{{ $deals?->links() }}</div>
            </div>
        @endif
    @endif
</div>
