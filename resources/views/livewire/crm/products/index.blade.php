@php
    use App\Support\PersianNumber;

    $categoryIcons = [
        'monitor' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 5.5A1.5 1.5 0 0 1 5.5 4h13A1.5 1.5 0 0 1 20 5.5v9A1.5 1.5 0 0 1 18.5 16H5.5A1.5 1.5 0 0 1 4 14.5v-9ZM8 20h8M12 16v4" />',
        'chip' => '<rect x="7" y="7" width="10" height="10" rx="1.5" /><path stroke-linecap="round" d="M10 4v3M14 4v3M10 17v3M14 17v3M4 10h3M4 14h3M17 10h3M17 14h3" />',
        'gear' => '<circle cx="12" cy="12" r="3" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z" />',
        'layers' => '<path stroke-linecap="round" stroke-linejoin="round" d="m12 3 8 4.5-8 4.5L4 7.5 12 3Zm-8 9 8 4.5 8-4.5M4 16.5 12 21l8-4.5" />',
        'graduation' => '<path stroke-linecap="round" stroke-linejoin="round" d="M22 10 12 5 2 10l10 5 10-5Zm-10 5v5m-6-2.5v-4.2l6 3 6-3v4.2A12.3 12.3 0 0 1 12 21a12.3 12.3 0 0 1-6-1.7Z" />',
        'users' => '<path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5" /><rect x="14" y="3" width="7" height="7" rx="1.5" /><rect x="3" y="14" width="7" height="7" rx="1.5" /><rect x="14" y="14" width="7" height="7" rx="1.5" />',
        'box' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M3.3 7 12 12l8.7-5M12 22V12" />',
    ];
@endphp

<div class="saas-page crm-products-page space-y-5">
    <header class="crm-products-header">
        <div class="crm-products-header__title">
            <div class="crm-products-header__heading">
                <span class="crm-products-header__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.3 7 12 12l8.7-5M12 22V12" />
                    </svg>
                </span>
                <h1>محصولات و خدمات</h1>
            </div>
            <p>
                کاتالوگ فروش سازمان برای فرصت‌ها و پیگیری‌های CRM —
                {{ PersianNumber::format($counts['product']) }} محصول |
                {{ PersianNumber::format($counts['service']) }} خدمت
            </p>
        </div>

        @if ($canManage)
            <div class="crm-products-header__actions">
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
                <button type="button" class="saas-btn-brand text-sm" wire:click="openCreate">
                    مورد جدید
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                    </svg>
                </button>
            </div>
        @endif
    </header>

    <div class="crm-products-toolbar">
        <div class="crm-products-type-tabs" role="toolbar" aria-label="فیلتر نوع">
            @foreach (['all' => 'همه', 'product' => 'محصول', 'service' => 'خدمت'] as $value => $label)
                <button
                    type="button"
                    wire:click="setType('{{ $value }}')"
                    @class(['crm-products-type-tab', 'is-active' => $type === $value])
                >{{ $label }}</button>
            @endforeach
        </div>

        <label class="crm-products-search">
            <span class="sr-only">جستجو</span>
            <span class="crm-products-search__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="11" cy="11" r="7" />
                    <path stroke-linecap="round" d="m20 20-3.5-3.5" />
                </svg>
            </span>
            <input
                wire:model.live.debounce.300ms="searchInput"
                type="search"
                class="saas-input"
                placeholder="جستجو نام یا کد محصول، خدمت، دسته‌بندی..."
            >
        </label>
    </div>

    <section class="crm-products-categories" aria-label="دسته‌بندی محصولات">
        <div class="crm-products-categories__intro">
            <div class="crm-products-categories__intro-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m12 3 8 4.5-8 4.5L4 7.5 12 3Zm-8 9 8 4.5 8-4.5M4 16.5 12 21l8-4.5" />
                </svg>
            </div>
            <div>
                <h2>دسته‌بندی محصولات</h2>
                <p>بر اساس دسته‌بندی، موارد را فیلتر کنید</p>
            </div>
        </div>

        <div class="crm-products-categories__track">
            @if ($categories->isNotEmpty())
                <button
                    type="button"
                    wire:click="setCategory('all')"
                    @class(['crm-products-category-card', 'is-active' => $category === 'all'])
                >
                    <span class="crm-products-category-card__icon tone-violet" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">{!! $categoryIcons['grid'] !!}</svg>
                    </span>
                    <span class="crm-products-category-card__label">همه</span>
                </button>

                @foreach ($categories as $item)
                    <button
                        type="button"
                        wire:key="category-{{ $item->id }}"
                        wire:click="setCategory('{{ $item->id }}')"
                        @class(['crm-products-category-card', 'is-active' => $category === (string) $item->id])
                    >
                        <span class="crm-products-category-card__icon tone-{{ $item->tone }}" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                {!! $categoryIcons[$item->icon] ?? $categoryIcons['layers'] !!}
                            </svg>
                        </span>
                        <span class="crm-products-category-card__label">{{ $item->name }}</span>
                    </button>
                @endforeach
            @endif

            @if ($canManage)
                <button
                    type="button"
                    class="crm-products-category-card crm-products-category-card--add"
                    wire:click="openCategoryForm"
                >
                    <span class="crm-products-category-card__icon tone-muted" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                        </svg>
                    </span>
                    <span class="crm-products-category-card__label">افزودن دسته‌بندی</span>
                </button>
            @endif
        </div>
    </section>

    @if ($products->isEmpty())
        <section class="crm-products-empty">
            <div class="crm-products-empty__art" aria-hidden="true">
                <div class="crm-products-empty__blob crm-products-empty__blob--a"></div>
                <div class="crm-products-empty__blob crm-products-empty__blob--b"></div>
                <div class="crm-products-empty__spark crm-products-empty__spark--1"></div>
                <div class="crm-products-empty__spark crm-products-empty__spark--2"></div>
                <div class="crm-products-empty__spark crm-products-empty__spark--3"></div>
                <div class="crm-products-empty__box">
                    <svg viewBox="0 0 120 100" fill="none">
                        <path d="M20 38 60 18l40 20v36c0 6-18 14-40 14S20 80 20 74V38Z" fill="#EDE9FE"/>
                        <path d="M20 38 60 58l40-20" stroke="#7C25FD" stroke-width="2.5" stroke-linejoin="round"/>
                        <path d="M60 58v30" stroke="#7C25FD" stroke-width="2.5"/>
                        <path d="M20 38 60 18l40 20" stroke="#7C25FD" stroke-width="2.5" stroke-linejoin="round"/>
                        <rect x="44" y="8" width="32" height="40" rx="4" fill="#fff" stroke="#C4B5FD" stroke-width="2"/>
                        <path d="M52 20h16M52 28h12M52 36h14" stroke="#A78BFA" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>
            </div>
            <h2>هنوز موردی ثبت نشده</h2>
            <p>محصولات و خدمات سازمان را اینجا وارد کنید تا در فروش و پیگیری CRM در دسترس باشند.</p>
            @if ($canManage)
                <button type="button" class="saas-btn-brand mt-6 text-sm" wire:click="openCreate">
                    مورد جدید
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                    </svg>
                </button>
            @endif
        </section>
    @else
        <section class="saas-card overflow-hidden !p-0">
            <div class="overflow-x-auto">
                <table class="saas-table crm-products-table">
                    <colgroup>
                        <col class="crm-products-col-name">
                        <col class="crm-products-col-type">
                        <col class="crm-products-col-category">
                        <col class="crm-products-col-code">
                        <col class="crm-products-col-price">
                        <col class="crm-products-col-status">
                        @if ($canManage)
                            <col class="crm-products-col-actions">
                        @endif
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="col">نام</th>
                            <th scope="col">نوع</th>
                            <th scope="col">دسته‌بندی</th>
                            <th scope="col">کد</th>
                            <th scope="col">قیمت</th>
                            <th scope="col">وضعیت</th>
                            @if ($canManage)
                                <th scope="col">عملیات</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($products as $product)
                            <tr wire:key="product-{{ $product->id }}">
                                <td>
                                    <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $product->name }}</div>
                                    @if (filled($product->description))
                                        <p class="mt-1 line-clamp-2 text-sm leading-6 text-zinc-500">{{ $product->description }}</p>
                                    @endif
                                </td>
                                <td>
                                    <span class="inline-flex items-center gap-1.5 text-sm text-zinc-700 dark:text-zinc-300">
                                        <span
                                            @class([
                                                'h-2 w-2 shrink-0 rounded-full',
                                                'bg-brand' => $product->type->value === 'product',
                                                'bg-sky-400' => $product->type->value === 'service',
                                            ])
                                            aria-hidden="true"
                                        ></span>
                                        {{ $product->type->label() }}
                                    </span>
                                </td>
                                <td>{{ $product->category?->name ?: '—' }}</td>
                                <td>{{ $product->code ?: '—' }}</td>
                                <td>{{ $product->formattedPrice() }}</td>
                                <td>
                                    @if ($product->is_active)
                                        <span class="font-medium text-emerald-600">فعال</span>
                                    @else
                                        <span class="font-medium text-zinc-500">غیرفعال</span>
                                    @endif
                                </td>
                                @if ($canManage)
                                    <td>
                                        <div class="crm-products-actions">
                                            <button
                                                type="button"
                                                class="text-sm font-medium text-brand hover:underline"
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
                        <span class="mb-1 block text-zinc-600">دسته‌بندی</span>
                        <select wire:model="categoryInput" class="saas-input">
                            <option value="">بدون دسته‌بندی</option>
                            @foreach ($categories as $item)
                                <option value="{{ $item->id }}">{{ $item->name }}</option>
                            @endforeach
                        </select>
                        @error('category_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </label>
                </div>

                <label class="block text-sm">
                    <span class="mb-1 block text-zinc-600">کد</span>
                    <input wire:model="code" class="saas-input" maxlength="64" placeholder="اختیاری">
                    @error('code') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </label>

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
                    <input type="checkbox" wire:model="isActive" class="rounded border-zinc-300 text-brand focus:ring-brand/40">
                    فعال
                </label>

                <div class="flex justify-end gap-2">
                    <button type="button" class="saas-btn-secondary text-sm" wire:click="closeForm">انصراف</button>
                    <button type="submit" class="saas-btn-brand text-sm" wire:loading.attr="disabled">
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
                    <button type="submit" class="saas-btn-brand text-sm" wire:loading.attr="disabled">ایمپورت</button>
                </div>
            </form>
        </div>
    @endif

    @if ($showCategoryForm)
        <div class="fixed inset-0 z-40 flex items-end justify-center bg-zinc-950/40 p-4 sm:items-center" wire:click.self="closeCategoryForm">
            <form wire:submit="saveCategory" class="saas-card w-full max-w-md space-y-3 p-4">
                <h2 class="text-lg font-semibold">افزودن دسته‌بندی</h2>
                <label class="block text-sm">
                    <span class="mb-1 block text-zinc-600">نام دسته‌بندی</span>
                    <input wire:model="categoryName" class="saas-input" required maxlength="80" placeholder="مثلاً تجهیزات شبکه">
                    @error('category_name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </label>
                <div class="flex justify-end gap-2">
                    <button type="button" class="saas-btn-secondary text-sm" wire:click="closeCategoryForm">انصراف</button>
                    <button type="submit" class="saas-btn-brand text-sm" wire:loading.attr="disabled">ثبت</button>
                </div>
            </form>
        </div>
    @endif
</div>
