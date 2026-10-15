@php
    use App\Support\PersianNumber;
@endphp

<div class="saas-page space-y-6">
    <nav class="text-sm text-zinc-500" aria-label="مسیر">
        <span>CRM</span>
        <span class="mx-2">/</span>
        <span class="text-zinc-800 dark:text-zinc-200">وظایف و پیگیری‌ها</span>
    </nav>

    <x-saas.page-header title="وظایف و پیگیری‌ها" description="پیگیری‌های دستی و پیشنهادهای تحلیل تماس در CRM داخلی آوایار.">
        <x-slot:actions>
            @if ($canCreate)
                <button type="button" class="saas-btn-primary text-sm" wire:click="$set('showCreate', true)">وظیفه جدید</button>
            @endif
        </x-slot:actions>
    </x-saas.page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-saas.stat-card label="باز" :value="PersianNumber::format($summary['open_count'])" />
        <x-saas.stat-card label="امروز" :value="PersianNumber::format($summary['today_count'])" />
        <x-saas.stat-card label="عقب‌افتاده" :value="PersianNumber::format($summary['overdue_count'])" tone="bad" />
        <x-saas.stat-card label="تکمیل ۳۰ روز" :value="PersianNumber::format($summary['completed_30d'])" tone="good" :hint="$summary['on_time_rate_30d'] === null ? null : 'به‌موقع: '.PersianNumber::format($summary['on_time_rate_30d'], 1).'٪'" />
    </div>

    @if ($suggestions->isNotEmpty())
        <section class="saas-card space-y-3 p-4">
            <h2 class="text-lg font-semibold">پیشنهادهای تحلیل تماس</h2>
            <p class="text-sm text-zinc-500">ساخت خودکار خاموش است؛ پس از بررسی تأیید یا رد کنید.</p>
            @foreach ($suggestions as $suggestion)
                <article class="rounded-lg border border-indigo-100 bg-indigo-50/40 p-3" wire:key="task-sug-{{ $suggestion->id }}">
                    <p class="font-medium">{{ $suggestion->proposed_title }}</p>
                    <p class="mt-1 text-sm text-zinc-600">{{ $suggestion->customer?->displayName() }} · موعد پیشنهادی: {{ $suggestion->proposed_due_at ? shamsi($suggestion->proposed_due_at) : '—' }}</p>
                    <div class="mt-3 flex gap-2">
                        <button type="button" class="saas-btn-primary text-sm" wire:click="acceptSuggestion({{ $suggestion->id }})">تأیید و ساخت وظیفه</button>
                        <button type="button" class="saas-btn-secondary text-sm" wire:click="dismissSuggestion({{ $suggestion->id }})">رد</button>
                    </div>
                </article>
            @endforeach
        </section>
    @endif

    <section class="saas-card space-y-4 p-4">
        <div class="grid gap-3 md:grid-cols-4">
            <input wire:model.live.debounce.300ms="search" class="saas-input md:col-span-2" placeholder="جست‌وجوی عنوان">
            <select wire:model.live="status" class="saas-input">
                <option value="open">باز</option>
                <option value="today">امروز</option>
                <option value="overdue">عقب‌افتاده</option>
                <option value="completed">انجام‌شده</option>
                <option value="cancelled">لغوشده</option>
                <option value="pending">در انتظار</option>
                <option value="in_progress">در حال انجام</option>
                <option value="all">همه</option>
            </select>
            <select wire:model.live="assignee" class="saas-input">
                <option value="all">همه مسئول‌ها</option>
                <option value="mine">من</option>
                @if ($isManager)
                    <option value="unassigned">بدون مسئول</option>
                @endif
            </select>
        </div>

        @if ($tasks->isEmpty())
            <x-saas.empty-state title="وظیفه‌ای نیست" description="با ساخت وظیفه یا تأیید پیشنهاد تحلیل، این فهرست پر می‌شود." />
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-zinc-500">
                        <tr class="border-b border-zinc-200 dark:border-zinc-800">
                            <th class="px-2 py-2 text-start font-medium">عنوان</th>
                            <th class="px-2 py-2 text-start font-medium">وضعیت</th>
                            <th class="px-2 py-2 text-start font-medium">مسئول</th>
                            <th class="px-2 py-2 text-start font-medium">موعد</th>
                            <th class="px-2 py-2 text-start font-medium">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tasks as $task)
                            <tr class="border-b border-zinc-100 dark:border-zinc-900" wire:key="task-row-{{ $task->id }}">
                                <td class="px-2 py-3">
                                    <a href="{{ route($portal.'.crm.tasks.show', $task) }}" class="font-medium text-indigo-700 hover:underline" wire:navigate>{{ $task->title }}</a>
                                    <p class="text-xs text-zinc-500">{{ $task->customer?->displayName() ?: ($task->deal?->title ?: '—') }}</p>
                                    @if ($task->isOverdue())
                                        <span class="text-xs font-medium text-rose-600">عقب‌افتاده</span>
                                    @endif
                                </td>
                                <td class="px-2 py-3">{{ $task->status->label() }}</td>
                                <td class="px-2 py-3">{{ $task->assignee?->full_name ?: '—' }}</td>
                                <td class="px-2 py-3">{{ $task->due_at ? shamsi($task->due_at) : '—' }}</td>
                                <td class="px-2 py-3">
                                    @if ($task->status->isOpen())
                                        <button type="button" class="text-xs text-emerald-700" wire:click="complete({{ $task->id }})">تکمیل</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div>{{ $tasks->links() }}</div>
        @endif
    </section>

    @if ($showCreate)
        <div class="fixed inset-0 z-40 flex items-end justify-center bg-zinc-950/40 p-4 sm:items-center" wire:click.self="$set('showCreate', false)">
            <form wire:submit="createTask" class="saas-card w-full max-w-lg space-y-3 p-4">
                <h2 class="text-lg font-semibold">وظیفه جدید</h2>
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
                    <span class="mb-1 block text-zinc-600">شخص</span>
                    <input wire:model.live.debounce.300ms="customerQuery" class="saas-input" placeholder="جست‌وجو">
                    @foreach ($customerChoices as $customer)
                        <button type="button" class="mt-1 block text-sm text-indigo-700" wire:click="chooseCustomer({{ $customer->id }})">{{ $customer->displayName() }}</button>
                    @endforeach
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-zinc-600">معامله</span>
                    <select wire:model="dealId" class="saas-input">
                        <option value="">بدون معامله</option>
                        @foreach ($deals as $deal)
                            <option value="{{ $deal->id }}">{{ $deal->title }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="flex justify-end gap-2">
                    <button type="button" class="saas-btn-secondary text-sm" wire:click="$set('showCreate', false)">انصراف</button>
                    <button type="submit" class="saas-btn-primary text-sm">ثبت</button>
                </div>
            </form>
        </div>
    @endif
</div>
