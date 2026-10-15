@php
    use App\Support\PersianNumber;
@endphp

<div class="saas-page space-y-6">
    <x-saas.page-header
        title="یادآوری‌ها"
        :description="PersianNumber::format($summary['open_count']).' باز • '.PersianNumber::format($summary['overdue_count']).' عقب‌افتاده — به مخاطب و فرصت هم وصل می‌شوند'"
    >
        <x-slot:actions>
            @if ($canCreate)
                <button type="button" class="saas-btn-primary text-sm" wire:click="openCreate">یادآوری جدید</button>
            @endif
        </x-slot:actions>
    </x-saas.page-header>

    <div class="flex flex-wrap items-center gap-2" role="toolbar" aria-label="فیلتر یادآوری‌ها">
        <button
            type="button"
            wire:click="setStatus('open')"
            @class([
                'inline-flex items-center rounded-full border px-3.5 py-1.5 text-sm transition',
                'border-indigo-200 bg-indigo-50 font-semibold text-indigo-800 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-200' => $status === 'open',
                'border-zinc-200 bg-white text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300' => $status !== 'open',
            ])
        >باز</button>
        <button
            type="button"
            wire:click="setStatus('all')"
            @class([
                'inline-flex items-center rounded-full border px-3.5 py-1.5 text-sm transition',
                'border-indigo-200 bg-indigo-50 font-semibold text-indigo-800 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-200' => $status === 'all',
                'border-zinc-200 bg-white text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300' => $status !== 'all',
            ])
        >همه</button>
        <button
            type="button"
            wire:click="setScope('all')"
            @class([
                'inline-flex items-center rounded-full border px-3.5 py-1.5 text-sm transition',
                'border-indigo-200 bg-indigo-50 font-semibold text-indigo-800 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-200' => $scope === 'all',
                'border-zinc-200 bg-white text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300' => $scope !== 'all',
            ])
        >همه منابع</button>
        <button
            type="button"
            wire:click="setScope('sales')"
            @class([
                'inline-flex items-center rounded-full border px-3.5 py-1.5 text-sm transition',
                'border-indigo-200 bg-indigo-50 font-semibold text-indigo-800 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-200' => $scope === 'sales',
                'border-zinc-200 bg-white text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300' => $scope !== 'sales',
            ])
        >پیگیری فروش</button>
    </div>

    @if ($reminders->isEmpty())
        <x-saas.empty-state
            title="یادآوری‌ای نیست"
            description="با ساخت یادآوری جدید و اتصال به مخاطب یا فرصت، این فهرست پر می‌شود."
        >
            @if ($canCreate)
                <button type="button" class="saas-btn-primary mt-6 text-sm" wire:click="openCreate">یادآوری جدید</button>
            @endif
        </x-saas.empty-state>
    @else
        <div class="space-y-3">
            @foreach ($reminders as $reminder)
                <article
                    wire:key="reminder-{{ $reminder->id }}"
                    @class([
                        'saas-card flex flex-col gap-4 p-4 sm:flex-row sm:items-start sm:justify-between',
                        'border-rose-200 dark:border-rose-900/60' => $reminder->isOverdue(),
                    ])
                >
                    <div class="min-w-0 flex-1 space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ $reminder->title }}</h2>
                            <span class="inline-flex items-center rounded-md bg-zinc-100 px-2 py-0.5 text-xs text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                {{ $this->sourceBadge($reminder) }}
                            </span>
                        </div>

                        @if (filled($reminder->description))
                            <p class="whitespace-pre-line text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $reminder->description }}</p>
                        @endif

                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-zinc-500">
                            @if ($reminder->due_at)
                                <span>{{ shamsi($reminder->due_at, 'time') }} {{ shamsi($reminder->due_at) }}</span>
                            @else
                                <span>بدون موعد</span>
                            @endif
                            @if ($reminder->isOverdue())
                                <span class="font-medium text-rose-600">عقب‌افتاده</span>
                            @elseif (! $reminder->status->isOpen())
                                <span>{{ $reminder->status->label() }}</span>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-zinc-600 dark:text-zinc-300">
                            <span>
                                مخاطب:
                                @if ($reminder->customer)
                                    <a
                                        href="{{ route($portal.'.customers.show', $reminder->customer) }}"
                                        class="text-indigo-700 hover:underline dark:text-indigo-300"
                                        wire:navigate
                                    >{{ $reminder->customer->displayName() }}</a>
                                @else
                                    —
                                @endif
                            </span>
                            <span>
                                فرصت:
                                @if ($reminder->deal)
                                    <a
                                        href="{{ route($portal.'.crm.deals.show', $reminder->deal) }}"
                                        class="text-indigo-700 hover:underline dark:text-indigo-300"
                                        wire:navigate
                                    >{{ $reminder->deal->title }}</a>
                                @else
                                    —
                                @endif
                            </span>
                        </div>
                    </div>

                    @if ($reminder->status->isOpen())
                        <div class="flex shrink-0 flex-wrap items-center gap-2 sm:justify-end">
                            <button type="button" class="saas-btn-secondary text-sm" wire:click="openEdit({{ $reminder->id }})">ویرایش</button>
                            <button type="button" class="saas-btn-secondary text-sm" wire:click="complete({{ $reminder->id }})">انجام شد</button>
                            <button
                                type="button"
                                class="saas-btn-secondary text-sm"
                                wire:click="remove({{ $reminder->id }})"
                                wire:confirm="این یادآوری حذف شود؟"
                            >حذف</button>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
        <div>{{ $reminders->links() }}</div>
    @endif

    @if ($showForm)
        <div class="fixed inset-0 z-40 flex items-end justify-center bg-zinc-950/40 p-4 sm:items-center" wire:click.self="closeForm">
            <form wire:submit="save" class="saas-card w-full max-w-lg space-y-3 p-4">
                <h2 class="text-lg font-semibold">{{ $editingId ? 'ویرایش یادآوری' : 'یادآوری جدید' }}</h2>

                <label class="block text-sm">
                    <span class="mb-1 block text-zinc-600">عنوان</span>
                    <input wire:model="title" class="saas-input" required>
                    @error('title') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </label>

                <label class="block text-sm">
                    <span class="mb-1 block text-zinc-600">توضیحات</span>
                    <textarea wire:model="description" rows="3" class="saas-input"></textarea>
                </label>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm">
                        <span class="mb-1 block text-zinc-600">اولویت</span>
                        <select wire:model="priorityInput" class="saas-input">
                            @foreach ($priorities as $item)
                                <option value="{{ $item->value }}">{{ $item->label() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block text-zinc-600">موعد</span>
                        <input wire:model="dueAt" type="datetime-local" class="saas-input">
                        @error('due_at') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </label>
                </div>

                @if ($isManager)
                    <label class="block text-sm">
                        <span class="mb-1 block text-zinc-600">مسئول</span>
                        <select wire:model="assigneeId" class="saas-input">
                            <option value="">بدون مسئول</option>
                            @foreach ($owners as $member)
                                <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif

                <label class="block text-sm">
                    <span class="mb-1 block text-zinc-600">مخاطب</span>
                    <input wire:model.live.debounce.300ms="customerQuery" class="saas-input" placeholder="جست‌وجو نام یا شماره">
                    @foreach ($customerChoices as $customer)
                        <button type="button" class="mt-1 block text-sm text-indigo-700" wire:click="chooseCustomer({{ $customer->id }})">
                            {{ $customer->displayName() }}
                        </button>
                    @endforeach
                </label>

                <label class="block text-sm">
                    <span class="mb-1 block text-zinc-600">فرصت</span>
                    <select wire:model="dealId" class="saas-input">
                        <option value="">بدون فرصت</option>
                        @foreach ($deals as $deal)
                            <option value="{{ $deal->id }}">{{ $deal->title }}</option>
                        @endforeach
                    </select>
                </label>

                @error('task') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                @error('status') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                <div class="flex justify-end gap-2">
                    <button type="button" class="saas-btn-secondary text-sm" wire:click="closeForm">انصراف</button>
                    <button type="submit" class="saas-btn-primary text-sm" wire:loading.attr="disabled">
                        {{ $editingId ? 'ذخیره' : 'ثبت' }}
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
