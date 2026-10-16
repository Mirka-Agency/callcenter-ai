@php
    use App\Support\PersianNumber;
@endphp

<div class="saas-page space-y-5">
    <x-saas.page-header
        title="همه کاریزها"
        description="کاریزهای فروش و پشتیبانی سازمان را ببینید، ویرایش کنید یا کاریز جدید بسازید."
    >
        <x-slot:actions>
            <a href="{{ $dealsUrl }}" class="saas-btn-secondary text-sm" wire:navigate>
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 5h4v14H4V5Zm6 0h4v9h-4V5Zm6 0h4v6h-4V5Z" />
                </svg>
                بازگشت به معاملات
            </a>
            <a href="{{ $createUrl }}" class="saas-btn-primary text-sm" wire:navigate>
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                </svg>
                کاریز جدید
            </a>
        </x-slot:actions>
    </x-saas.page-header>

    <label class="relative block max-w-md">
        <span class="sr-only">جستجو کاریز</span>
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
            placeholder="جستجو نام یا توضیح کاریز…"
        >
    </label>

    @if ($pipelines->isEmpty())
        <x-saas.empty-state
            title="هنوز کاریزی ساخته نشده"
            description="اولین کاریز فروش یا پشتیبانی را بسازید تا معاملات روی آن جریان بگیرند."
        >
            <a href="{{ $createUrl }}" class="saas-btn-primary mt-6 text-sm" wire:navigate>ساخت کاریز</a>
        </x-saas.empty-state>
    @else
        <section class="saas-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="saas-table min-w-full">
                    <thead>
                        <tr>
                            <th>نام</th>
                            <th>مراحل</th>
                            <th>معاملات</th>
                            <th>وضعیت</th>
                            <th class="text-end">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pipelines as $index => $pipeline)
                            <tr wire:key="pipeline-{{ $pipeline->id }}">
                                <td class="max-w-md">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $pipeline->name }}</span>
                                        @if ($pipeline->is_default)
                                            <span class="rounded-full bg-violet-50 px-2 py-0.5 text-xs font-medium text-violet-700 dark:bg-violet-950/40 dark:text-violet-300">پیش‌فرض</span>
                                        @endif
                                    </div>
                                    @if (filled($pipeline->description))
                                        <p class="mt-1 line-clamp-2 text-sm leading-6 text-zinc-500">{{ $pipeline->description }}</p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-sm text-zinc-700 dark:text-zinc-300">
                                    {{ PersianNumber::format($pipeline->stages_count) }} مرحله
                                </td>
                                <td class="whitespace-nowrap text-sm text-zinc-700 dark:text-zinc-300">
                                    {{ PersianNumber::format($pipeline->deals_count) }} معامله
                                </td>
                                <td>
                                    @if ($pipeline->is_active)
                                        <span class="text-sm font-medium text-emerald-600">فعال</span>
                                    @else
                                        <span class="text-sm font-medium text-zinc-500">غیرفعال</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        @if ($index === 0)
                                            <a
                                                href="{{ route($portal.'.crm.pipelines.edit', $pipeline) }}"
                                                class="saas-btn-secondary px-3 py-1.5 text-xs"
                                                wire:navigate
                                            >ویرایش</a>
                                        @else
                                            <button
                                                type="button"
                                                class="saas-btn-secondary px-3 py-1.5 text-xs"
                                                wire:click="setDefault({{ $pipeline->id }})"
                                                @disabled(! $pipeline->is_active)
                                            >پیش‌فرض</button>
                                            <a
                                                href="{{ route($portal.'.crm.pipelines.edit', $pipeline) }}"
                                                class="saas-btn-secondary px-3 py-1.5 text-xs"
                                                wire:navigate
                                            >ویرایش</a>
                                            <button
                                                type="button"
                                                class="saas-btn border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs text-rose-700 hover:bg-rose-100 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-300"
                                                wire:click="remove({{ $pipeline->id }})"
                                                wire:confirm="این کاریز حذف شود؟ فقط وقتی معامله نداشته باشد قابل حذف است."
                                            >حذف</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
