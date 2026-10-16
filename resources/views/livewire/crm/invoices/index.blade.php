@php
    use App\Application\Crm\Invoices\InvoiceMoney;
    use App\Domain\Crm\Invoices\InvoiceDocumentType;
    use App\Domain\Crm\Invoices\InvoiceStatus;
    use App\Support\JalaliDate;
@endphp

<div class="saas-page crm-documents-page space-y-5">
    <header class="crm-documents-header">
        <div class="crm-documents-header__title">
            <div class="crm-documents-header__heading">
                <span class="crm-documents-header__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 3h6l4 4v12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 3v4h4M9 12h6M9 16h4" />
                    </svg>
                </span>
                <div>
                    <h1>اسناد فروش</h1>
                    <p>مدیریت فاکتورها و پیش‌فاکتورها</p>
                </div>
            </div>
        </div>

        @if ($canManage)
            <div
                class="crm-documents-header__actions"
                x-data="{ open: false }"
                @click.outside="open = false"
                @keydown.escape.window="open = false"
            >
                <div class="relative">
                    <button
                        type="button"
                        class="saas-btn-brand text-sm"
                        @click="open = !open"
                        :aria-expanded="open"
                        aria-haspopup="menu"
                    >
                        افزودن سند فروش
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                        </svg>
                    </button>

                    <div
                        x-cloak
                        x-show="open"
                        x-transition.opacity
                        class="crm-documents-create-menu"
                        role="menu"
                        aria-label="نوع سند فروش"
                    >
                        <button
                            type="button"
                            role="menuitem"
                            class="crm-documents-create-menu__item"
                            wire:click="openCreate('invoice')"
                            @click="open = false"
                        >
                            <span class="crm-documents-create-menu__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 3h6l4 4v12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 3v4h4M9 13h6M9 17h4" />
                                </svg>
                            </span>
                            فاکتور
                        </button>
                        <button
                            type="button"
                            role="menuitem"
                            class="crm-documents-create-menu__item"
                            wire:click="openCreate('proforma')"
                            @click="open = false"
                        >
                            <span class="crm-documents-create-menu__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 3h6l4 4v12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 3v4h4M9 12h6" />
                                </svg>
                            </span>
                            پیش‌فاکتور
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </header>

    <nav class="crm-documents-tabs" aria-label="نمای اسناد فروش">
        @foreach ([
            'all' => 'همه',
            'issued' => 'فاکتورهای صادر شده',
            'drafts' => 'پیش‌نویس‌ها',
        ] as $value => $label)
            <button
                type="button"
                wire:click="setTab('{{ $value }}')"
                @class(['crm-documents-tab', 'is-active' => $tab === $value])
                @if ($tab === $value) aria-current="page" @endif
            >
                {{ $label }}
                @if (isset($counts[$value]))
                    <span class="crm-documents-tab__count">{{ JalaliDate::persianDigits($counts[$value]) }}</span>
                @endif
            </button>
        @endforeach
    </nav>

    <div class="crm-documents-toolbar">
        <label class="crm-documents-search">
            <span class="sr-only">جستجو</span>
            <span class="crm-documents-search__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="11" cy="11" r="7" />
                    <path stroke-linecap="round" d="m20 20-3.5-3.5" />
                </svg>
            </span>
            <input
                wire:model.live.debounce.300ms="search"
                type="search"
                class="saas-input"
                placeholder="جستجو در شماره، نام مخاطب، توضیحات..."
            >
        </label>

        @if ($showFilters)
            <label class="crm-documents-select">
                <span class="sr-only">وضعیت</span>
                <select wire:model.live="status" class="saas-input" @disabled($tab !== 'all')>
                    <option value="all">همه وضعیت</option>
                    @foreach ($statusOptions as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
            </label>

            <label class="crm-documents-select">
                <span class="sr-only">نوع سند</span>
                <select wire:model.live="type" class="saas-input" @disabled($tab === 'issued')>
                    <option value="all">همه انواع</option>
                    @foreach ($typeOptions as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
            </label>

            <label class="crm-documents-select">
                <span class="sr-only">مخاطب</span>
                <select wire:model.live="customerIdFilter" class="saas-input">
                    <option value="">همه مخاطبین</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->displayName() }}</option>
                    @endforeach
                </select>
            </label>

            <label class="crm-documents-date">
                <span class="sr-only">از تاریخ</span>
                <span class="crm-documents-date__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 3v3m10-3v3M4 9h16M6 5h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z" />
                    </svg>
                </span>
                <input
                    wire:model.live="fromDate"
                    type="date"
                    class="saas-input"
                    aria-label="از تاریخ"
                    title="از تاریخ"
                >
            </label>
        @endif

        <button
            type="button"
            @class(['crm-documents-filter-btn', 'is-active' => $showFilters])
            wire:click="toggleFilters"
            aria-expanded="{{ $showFilters ? 'true' : 'false' }}"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M7 12h10M10 18h4" />
            </svg>
            <span>فیلترها</span>
        </button>
    </div>

    <section class="saas-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="saas-table crm-documents-table min-w-[1100px]">
                <thead>
                    <tr>
                        <th scope="col">شماره سند</th>
                        <th scope="col">نوع سند</th>
                        <th scope="col">مخاطب</th>
                        <th scope="col">موضوع</th>
                        <th scope="col">مبلغ (ریال)</th>
                        <th scope="col">وضعیت</th>
                        <th scope="col">تاریخ ایجاد</th>
                        <th scope="col">تاریخ صدور</th>
                        <th scope="col" class="w-12"><span class="sr-only">عملیات</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $document)
                        @php
                            $showUrl = route($portal.'.crm.invoices.show', $document);
                            $isInvoice = $document->document_type === InvoiceDocumentType::Invoice;
                        @endphp
                        <tr
                            wire:key="invoice-{{ $document->id }}"
                            class="crm-documents-row"
                            data-row-href="{{ $showUrl }}"
                        >
                            <td class="whitespace-nowrap font-medium text-zinc-900 dark:text-zinc-100">
                                <a href="{{ $showUrl }}" wire:navigate class="crm-documents-number">
                                    {{ $document->displayNumber() }}
                                </a>
                            </td>
                            <td>
                                <span @class([
                                    'crm-documents-type',
                                    'is-invoice' => $isInvoice,
                                    'is-proforma' => ! $isInvoice,
                                ])>
                                    {{ $document->document_type->label() }}
                                </span>
                            </td>
                            <td class="max-w-[14rem]">
                                <div class="crm-documents-contact">
                                    <span class="crm-documents-contact__icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6" />
                                        </svg>
                                    </span>
                                    <span class="truncate">{{ $document->buyerDisplayName() }}</span>
                                </div>
                            </td>
                            <td class="max-w-[14rem] truncate text-sm text-zinc-600 dark:text-zinc-300">
                                {{ $document->subjectDisplay() }}
                            </td>
                            <td class="whitespace-nowrap tabular-nums text-sm text-zinc-800 dark:text-zinc-200">
                                {{ InvoiceMoney::formatDigits((int) $document->total_rials) }}
                            </td>
                            <td>
                                <span @class([
                                    'crm-documents-status',
                                    'is-draft' => $document->status === InvoiceStatus::Draft,
                                    'is-issued' => $document->status === InvoiceStatus::Issued,
                                ])>
                                    {{ $document->status->label() }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap text-sm text-zinc-500">
                                {{ JalaliDate::persianDigits(JalaliDate::date($document->created_at)) }}
                            </td>
                            <td class="whitespace-nowrap text-sm text-zinc-500">
                                {{ $document->issued_at ? JalaliDate::persianDigits(JalaliDate::date($document->issued_at)) : '—' }}
                            </td>
                            <td data-row-ignore>
                                <div
                                    class="relative flex justify-end"
                                    x-data="{ open: false }"
                                    @click.outside="open = false"
                                    @keydown.escape.window="open = false"
                                >
                                    <button
                                        type="button"
                                        class="crm-directory-menu-btn"
                                        @click.stop="open = !open"
                                        :aria-expanded="open"
                                        aria-haspopup="true"
                                        aria-label="عملیات سند {{ $document->displayNumber() }}"
                                    >
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                            <circle cx="12" cy="5" r="1.75" />
                                            <circle cx="12" cy="12" r="1.75" />
                                            <circle cx="12" cy="19" r="1.75" />
                                        </svg>
                                    </button>
                                    <div
                                        x-cloak
                                        x-show="open"
                                        x-transition.opacity
                                        class="crm-directory-menu"
                                        role="menu"
                                    >
                                        <a href="{{ $showUrl }}" wire:navigate role="menuitem" class="crm-directory-menu-item" @click="open = false">مشاهده</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-16 text-center text-sm text-zinc-500">
                                هنوز سندی نیست.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($invoices->total() > 0)
            <div class="border-t border-zinc-100 px-4 py-3 dark:border-zinc-800">
                @include('livewire.shared.customers.partials.directory-pagination', ['paginator' => $invoices])
            </div>
        @endif
    </section>

    @if ($showCreate && $canManage)
        <div class="fixed inset-0 z-40 flex items-end justify-center bg-zinc-950/40 p-4 sm:items-center" wire:click.self="closeCreate">
            <form wire:submit="createDraft" class="saas-card w-full max-w-md overflow-hidden p-0">
                <div class="flex items-start justify-between gap-3 border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
                    <div>
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">سند جدید</h2>
                        <p class="mt-1 text-sm leading-6 text-zinc-500">
                            پیش‌فاکتور یا فاکتور به‌صورت پیش‌نویس ساخته می‌شود. پیامک هنگام صدور قابل انتخاب است.
                        </p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800" wire:click="closeCreate" aria-label="بستن">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-4 px-5 py-4">
                    <div>
                        <span class="mb-1.5 block text-sm text-zinc-600">نوع سند</span>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach (['proforma' => 'پیش‌فاکتور', 'invoice' => 'فاکتور'] as $value => $label)
                                <button
                                    type="button"
                                    wire:click="$set('documentType', '{{ $value }}')"
                                    @class([
                                        'rounded-lg border px-3 py-2.5 text-sm font-medium transition',
                                        'border-brand bg-brand text-white' => $documentType === $value,
                                        'border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300' => $documentType !== $value,
                                    ])
                                >{{ $label }}</button>
                            @endforeach
                        </div>
                        @error('document_type') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <label class="block text-sm">
                        <span class="mb-1.5 block text-zinc-600">مشتری</span>
                        <select wire:model="customerId" class="saas-input">
                            <option value="">— انتخاب اختیاری —</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}">
                                    {{ $customer->displayName() }}
                                    @if (filled($customer->phone_number))
                                        — {{ $customer->phone_number }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1.5 text-xs text-zinc-500">اختیاری؛ نام و شماره خریدار از مخاطب پر می‌شود.</p>
                        @error('customer_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </label>
                </div>

                <div class="flex items-center justify-between gap-3 border-t border-zinc-100 px-5 py-4 dark:border-zinc-800">
                    <button type="button" class="text-sm font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-300" wire:click="closeCreate">انصراف</button>
                    <button type="submit" class="saas-btn-brand text-sm" wire:loading.attr="disabled">ایجاد پیش‌نویس</button>
                </div>
            </form>
        </div>
    @endif
</div>
