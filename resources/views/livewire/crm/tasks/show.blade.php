<div class="saas-page space-y-6">
    <nav class="text-sm text-zinc-500" aria-label="مسیر">
        <a href="{{ $backUrl }}" class="hover:text-indigo-600" wire:navigate>وظایف</a>
        <span class="mx-2">/</span>
        <span class="text-zinc-800 dark:text-zinc-200">{{ $task->title }}</span>
    </nav>

    <x-saas.page-header :title="$task->title" :description="$task->status->label().' · '.$task->priority->label().' · منبع '.$task->source->label()">
        <x-slot:actions>
            @if ($canUpdate && $task->status->isOpen())
                @if ($task->status->value === 'pending')
                    <button type="button" class="saas-btn-secondary text-sm" wire:click="start">شروع</button>
                @endif
                <button type="button" class="saas-btn-primary text-sm" wire:click="complete">تکمیل</button>
                <button type="button" class="saas-btn-secondary text-sm" wire:click="cancel" wire:confirm="وظیفه لغو شود؟">لغو</button>
            @endif
            @if ($canReopen && $task->status->isTerminal())
                <button type="button" class="saas-btn-secondary text-sm" wire:click="reopen">بازگشایی</button>
            @endif
        </x-slot:actions>
    </x-saas.page-header>

    @if ($task->isOverdue())
        <p class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">این وظیفه از موعد گذشته است.</p>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="saas-card space-y-4 p-4 lg:col-span-2">
            <h2 class="text-lg font-semibold">اطلاعات وظیفه</h2>
            <form wire:submit="save" class="grid gap-3 md:grid-cols-2">
                <label class="block text-sm md:col-span-2">
                    <span class="mb-1 block text-zinc-600">عنوان</span>
                    <input wire:model="title" class="saas-input" @disabled(! $canUpdate || ! $task->status->isOpen())>
                    @error('title') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </label>
                <label class="block text-sm md:col-span-2">
                    <span class="mb-1 block text-zinc-600">توضیحات</span>
                    <textarea wire:model="description" rows="4" class="saas-input" @disabled(! $canUpdate || ! $task->status->isOpen())></textarea>
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-zinc-600">اولویت</span>
                    <select wire:model="priority" class="saas-input" @disabled(! $canUpdate || ! $task->status->isOpen())>
                        @foreach ($priorities as $item)
                            <option value="{{ $item->value }}">{{ $item->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-zinc-600">موعد</span>
                    <input wire:model="dueAt" type="datetime-local" class="saas-input" @disabled(! $canUpdate || ! $task->status->isOpen())>
                    @if ($task->due_source)
                        <p class="mt-1 text-xs text-zinc-500">مبنای موعد: {{ $task->due_source->label() }}</p>
                    @endif
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-zinc-600">شخص</span>
                    <select wire:model="customerId" class="saas-input" @disabled(! $canUpdate || ! $task->status->isOpen())>
                        <option value="">—</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->displayName() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-zinc-600">معامله</span>
                    <select wire:model="dealId" class="saas-input" @disabled(! $canUpdate || ! $task->status->isOpen())>
                        <option value="">—</option>
                        @foreach ($deals as $deal)
                            <option value="{{ $deal->id }}">{{ $deal->title }}</option>
                        @endforeach
                    </select>
                </label>
                @if ($canUpdate && $task->status->isOpen())
                    <div class="md:col-span-2">
                        <button type="submit" class="saas-btn-primary text-sm">ذخیره</button>
                    </div>
                @endif
                @error('task') <p class="text-sm text-rose-600 md:col-span-2">{{ $message }}</p> @enderror
                @error('status') <p class="text-sm text-rose-600 md:col-span-2">{{ $message }}</p> @enderror
            </form>
            @if ($canUpdate && $task->status->isOpen())
                <label class="block text-sm border-t border-zinc-100 pt-4">
                    <span class="mb-1 block text-zinc-600">نتیجه انجام / دلیل لغو</span>
                    <input wire:model="outcome" class="saas-input" placeholder="اختیاری">
                </label>
            @endif
            @if ($task->completion_outcome)
                <p class="text-sm text-zinc-600">نتیجه: {{ $task->completion_outcome }}</p>
            @endif
        </section>

        <div class="space-y-6">
            <section class="saas-card space-y-3 p-4">
                <h2 class="text-lg font-semibold">مسئول</h2>
                @if ($canAssign && $task->status->isOpen())
                    <select wire:model="assigneeId" class="saas-input">
                        <option value="">بدون مسئول</option>
                        @foreach ($owners as $member)
                            <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="saas-btn-secondary text-sm" wire:click="assign">ثبت مسئول</button>
                @else
                    <p class="text-sm">{{ $task->assignee?->full_name ?: 'بدون مسئول' }}</p>
                @endif
            </section>
            <section class="saas-card space-y-2 p-4 text-sm">
                <h2 class="text-lg font-semibold">ارتباط‌ها</h2>
                <p>شخص: {{ $task->customer?->displayName() ?: '—' }}</p>
                <p>شرکت: {{ $task->company?->name ?: '—' }}</p>
                <p>معامله:
                    @if ($task->deal)
                        <a href="{{ route($portal.'.crm.deals.show', $task->deal) }}" class="text-indigo-700" wire:navigate>{{ $task->deal->title }}</a>
                    @else
                        —
                    @endif
                </p>
                @if ($task->analysis)
                    <p class="text-zinc-500">منبع AI: تحلیل تماس #{{ $task->conversation_analysis_id }}</p>
                @endif
            </section>
        </div>
    </div>

    <section class="saas-card p-4">
        <h2 class="text-lg font-semibold">تاریخچه وظیفه</h2>
        <ul class="mt-4 space-y-2 text-sm">
            @forelse ($task->events->sortByDesc('id') as $event)
                <li wire:key="task-event-{{ $event->id }}">
                    {{ $event->type->label() }}
                    <span class="text-zinc-400">{{ shamsi($event->created_at) }} · {{ $event->actor?->name ?: 'سامانه' }} · {{ $event->source }}</span>
                    @if ($event->summary)
                        <p class="text-xs text-zinc-500">{{ $event->summary }}</p>
                    @endif
                </li>
            @empty
                <li class="text-zinc-500">هنوز رویدادی ثبت نشده است.</li>
            @endforelse
        </ul>
    </section>
</div>
