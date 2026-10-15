@php
    use App\Support\PersianNumber;
@endphp

<div class="saas-page space-y-5">
    <x-saas.page-header
        title="محصولات و خدمات"
        :description="'کاتالوگ فروش سازمان برای فرصت‌ها و پیگیری‌های CRM — '.PersianNumber::format($counts['product']).' محصول | '.PersianNumber::format($counts['service']).' خدمت'"
    >
        <x-slot:actions>
            @if ($canManage)
                <button type="button" class="saas-btn-secondary text-sm" wire:click="downloadTemplate">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v10m0 0 3.5-3.5M12 14l-3.5-3.5M5 18h14" />
                    </svg>
                    قالب CSV
                </button>
                <button type="button" class="saas-btn-secondary text-sm" wire:click="openImport">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 16V6m0 0 3.5 3.5M12 6 8.5 9.5M5 18h14" />
                    </svg>
                    ایمپورت
                </button>
                <button type="button" class="saas-btn-primary text-sm" wire:click="openCreate">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                    </svg>
                    مورد جدید
                </button>
            @endif
        </x-slot:actions>
    </x-saas.page-header>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap items-center gap-2" role="toolbar" aria-label="فیلتر نوع">
            @foreach (['all' => 'همه', 'product' => 'محصول', 'service' => 'خدمت'] as $value => $label)
                <button
                    type="button"
                    wire:click="setType('{{ $value }}')"
                    @class([
                        'inline-flex items-center rounded-full border px-3.5 py-1.5 text-sm transition',
                        'border-indigo-200 bg-indigo-50 font-semibold text-indigo-800 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-200' => $type === $value,
                        'border-zinc-200 bg-white text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300' => $type !== $value,
                    ])
                >{{ $label }}</button>
            @endforeach
        </div>

        <form wire:submit="applySearch" class="flex w-full gap-2 sm:max-w-md">
            <label class="relative min-w-0 flex-1">
                <span class="sr-only">جستجو</span>
                <input
                    wire:model="searchInput"
                    type="search"
                    class="saas-input"
                    placeholder="جستجو نام یا کد..."
                >
            </label>
            <button type="submit" class="saas-btn-secondary shrink-0 text-sm">جستجو</button>
        </form>
    </div>

    @if ($products->isEmpty())
        <x-saas.empty-state
            title="هنوز موردی ثبت نشده"
            description="محصولات و خدمات سازمان را اینجا وارد کنید تا در فروش و پیگیری CRM در دسترس باشند."
        >
            @if ($canManage)
                <button type="button" class="saas-btn-primary mt-6 text-sm" wire:click="openCreate">مورد جدید</button>
            @endif
        </x-saas.empty-state>
    @else
        <section class="saas-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="saas-table min-w-full">
                    <thead>
                        <tr>
                            <th>نام</th>
                            <th>نوع</th>
                            <th>کد</th>
                            <th>قیمت</th>
                            <th>وضعیت</th>
                            @if ($canManage)
                                <th class="text-end">عملیات</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($products as $product)
                            <tr wire:key="product-{{ $product->id }}">
                                <td class="max-w-md">
                                    <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $product->name }}</div>
                                    @if (filled($product->description))
                                        <p class="mt-1 line-clamp-2 text-sm leading-6 text-zinc-500">{{ $product->description }}</p>
                                    @endif
                                </td>
                                <td>
                                    <span class="inline-flex items-center gap-1.5 text-sm text-zinc-700 dark:text-zinc-300">
                                        <span
                                            @class([
                                                'h-2 w-2 rounded-full',
                                                'bg-indigo-600' => $product->type->value === 'product',
                                                'bg-sky-400' => $product->type->value === 'service',
                                            ])
                                            aria-hidden="true"
                                        ></span>
                                        {{ $product->type->label() }}
                                    </span>
                                </td>
                                <td class="text-sm text-zinc-600 dark:text-zinc-300">{{ $product->code ?: '—' }}</td>
                                <td class="whitespace-nowrap text-sm text-zinc-700 dark:text-zinc-200">{{ $product->formattedPrice() }}</td>
                                <td>
                                    @if ($product->is_active)
                                        <span class="text-sm font-medium text-emerald-600">فعال</span>
                                    @else
                                        <span class="text-sm font-medium text-zinc-500">غیرفعال</span>
                                    @endif
                                </td>
                                @if ($canManage)
                                    <td>
                                        <div class="flex items-center justify-end gap-2">
                                            <button
                                                type="button"
                                                class="text-sm font-medium text-indigo-700 hover:underline dark:text-indigo-300"
                                                wire:click="openEdit({{ $product->id }})"
                                            >ویرایش</button>
                                            <button
                                                type="button"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-zinc-500 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/40"
                                                wire:click="remove({{ $product->id }})"
                                                wire:confirm="این مورد حذف شود؟"
                                                aria-label="حذف {{ $product->name }}"
                                            >
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 7h14M10 11v6m4-6v6M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m-9 0 1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        <div>{{ $products->links() }}</div>
    @endif

    @if ($showForm)
        <div class="fixed inset-0 z-40 flex items-end justify-center bg-zinc-950/40 p-4 sm:items-center" wire:click.self="closeForm">
            <form wire:submit="save" class="saas-card w-full max-w-lg space-y-3 p-4">
                <h2 class="text-lg font-semibold">{{ $editingId ? 'ویرایش مورد' : 'مورد جدید' }}</h2>

                <label class="block text-sm">
                    <span class="mb-1 block text-zinc-600">نام</span>
                    <input wire:model="name" class="saas-input" required maxlength="180">
                    @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </label>

                <label class="block text-sm">
                    <span class="mb-1 block text-zinc-600">توضیحات</span>
                    <textarea wire:model="description" rows="3" class="saas-input"></textarea>
                    @error('description') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </label>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm">
                        <span class="mb-1 block text-zinc-600">نوع</span>
                        <select wire:model="typeInput" class="saas-input">
                            @foreach ($types as $item)
                                <option value="{{ $item->value }}">{{ $item->label() }}</option>
                            @endforeach
                        </select>
                        @error('type') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block text-zinc-600">کد</span>
                        <input wire:model="code" class="saas-input" maxlength="64" placeholder="اختیاری">
                        @error('code') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </label>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <label class="block text-sm sm:col-span-1">
                        <span class="mb-1 block text-zinc-600">قیمت (تومان)</span>
                        <input wire:model="priceTomans" class="saas-input" inputmode="numeric">
                        @error('price_tomans') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block text-zinc-600">مقدار واحد</span>
                        <input wire:model="unitQuantity" class="saas-input" inputmode="decimal" placeholder="مثلاً ۶۰۰۰">
                        @error('unit_quantity') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block text-zinc-600">برچسب واحد</span>
                        <input wire:model="unitLabel" class="saas-input" placeholder="عدد، دقیقه، ماه">
                        @error('unit_label') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </label>
                </div>

                <label class="inline-flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                    <input type="checkbox" wire:model="isActive" class="rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500">
                    فعال
                </label>

                <div class="flex justify-end gap-2">
                    <button type="button" class="saas-btn-secondary text-sm" wire:click="closeForm">انصراف</button>
                    <button type="submit" class="saas-btn-primary text-sm" wire:loading.attr="disabled">
                        {{ $editingId ? 'ذخیره' : 'ثبت' }}
                    </button>
                </div>
            </form>
        </div>
    @endif

    @if ($showImport)
        <div class="fixed inset-0 z-40 flex items-end justify-center bg-zinc-950/40 p-4 sm:items-center" wire:click.self="closeImport">
            <form wire:submit="import" class="saas-card w-full max-w-md space-y-3 p-4">
                <h2 class="text-lg font-semibold">ایمپورت CSV</h2>
                <p class="text-sm leading-6 text-zinc-600">
                    از قالب CSV استفاده کنید. اگر کد تکراری باشد، همان مورد به‌روزرسانی می‌شود.
                </p>
                <label class="block text-sm">
                    <span class="mb-1 block text-zinc-600">فایل</span>
                    <input type="file" wire:model="importFile" accept=".csv,text/csv" class="saas-input">
                    @error('importFile') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    @error('import_file') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </label>
                <div class="flex justify-end gap-2">
                    <button type="button" class="saas-btn-secondary text-sm" wire:click="closeImport">انصراف</button>
                    <button type="submit" class="saas-btn-primary text-sm" wire:loading.attr="disabled">ایمپورت</button>
                </div>
            </form>
        </div>
    @endif
</div>
