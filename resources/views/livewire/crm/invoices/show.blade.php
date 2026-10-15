@php
    use App\Application\Crm\Invoices\InvoiceMoney;
    use App\Support\JalaliDate;
    use App\Support\PersianNumber;

    $editable = $canManage && $invoice->isDraft();
@endphp

<div class="saas-page space-y-5" x-data>
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <a href="{{ route($portal.'.crm.invoices.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                بازگشت به لیست
            </a>
            <h1 class="mt-2 text-2xl font-bold text-zinc-900 dark:text-zinc-50">{{ $invoice->document_type->label() }}</h1>
            <span @class([
                'mt-2 inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                'bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300' => $invoice->isDraft(),
                'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' => $invoice->isIssued(),
            ])>
                {{ $invoice->status->label() }}
            </span>
        </div>

        @if ($canManage)
            <div class="flex flex-wrap items-center gap-2">
                @if ($editable)
                    <button type="button" class="saas-btn-secondary text-sm" wire:click="save" wire:loading.attr="disabled">ذخیره تغییرات</button>
                    <button type="button" class="saas-btn-primary text-sm" wire:click="issue" wire:loading.attr="disabled" wire:confirm="سند صادر شود؟ پس از صدور قابل ویرایش نیست.">صدور</button>
                @endif
                <button
                    type="button"
                    class="saas-btn-secondary text-sm text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30"
                    wire:click="remove"
                    wire:confirm="این سند حذف شود؟"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 7h14M10 11v6m4-6v6M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m-9 0 1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12" />
                    </svg>
                    حذف
                </button>
                <button type="button" class="saas-btn-secondary text-sm" onclick="window.print()">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 9V4h12v5M6 14H4a2 2 0 0 1-2-2v-1a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v1a2 2 0 0 1-2 2h-2m-12 0h12v6H6v-6Z" />
                    </svg>
                    چاپ
                </button>
            </div>
        @endif
    </div>

    @error('status') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
    @error('lines') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror

    <section class="saas-card space-y-4 p-5 print:hidden">
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="mb-1 block text-zinc-600">مخاطب</span>
                <select wire:model.live="customerId" class="saas-input" @disabled(! $editable)>
                    <option value="">انتخاب —</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->displayName() }}</option>
                    @endforeach
                </select>
                @error('customer_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>
            <label class="block text-sm">
                <span class="mb-1 block text-zinc-600">٪ مالیات</span>
                <input wire:model.live="taxPercent" class="saas-input" inputmode="decimal" @disabled(! $editable)>
                @error('tax_percent') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>
            <label class="block text-sm">
                <span class="mb-1 block text-zinc-600">نام خریدار</span>
                <input wire:model="buyerName" class="saas-input" @disabled(! $editable)>
                @error('buyer_name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>
            <label class="block text-sm">
                <span class="mb-1 block text-zinc-600">شرکت خریدار</span>
                <input wire:model="buyerCompany" class="saas-input" @disabled(! $editable)>
                @error('buyer_company') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>
            <label class="block text-sm">
                <span class="mb-1 block text-zinc-600">تلفن خریدار</span>
                <input wire:model="buyerPhone" class="saas-input" @disabled(! $editable)>
                @error('buyer_phone') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>
            <label class="block text-sm">
                <span class="mb-1 block text-zinc-600">کد ملی / شناسه ملی خریدار</span>
                <input wire:model="buyerNationalId" class="saas-input" @disabled(! $editable)>
                @error('buyer_national_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>
            <label class="block text-sm sm:col-span-2 lg:col-span-1">
                <span class="mb-1 block text-zinc-600">کد اقتصادی خریدار</span>
                <input wire:model="buyerEconomicCode" class="saas-input" @disabled(! $editable)>
                @error('buyer_economic_code') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>
            <label class="block text-sm sm:col-span-2">
                <span class="mb-1 block text-zinc-600">آدرس خریدار</span>
                <input wire:model="buyerAddress" class="saas-input" @disabled(! $editable)>
                @error('buyer_address') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>
            <label class="block text-sm sm:col-span-2">
                <span class="mb-1 block text-zinc-600">یادداشت</span>
                <input wire:model="notes" class="saas-input" @disabled(! $editable)>
                @error('notes') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>
        </div>

        @if ($editable)
            <div class="flex flex-col gap-2 border-t border-zinc-100 pt-4 sm:flex-row sm:items-end dark:border-zinc-800">
                <label class="block min-w-0 flex-1 text-sm">
                    <span class="mb-1 block text-zinc-600">افزودن از کاتالوگ</span>
                    <select wire:model="catalogProductId" class="saas-input">
                        <option value="">— محصول / خدمت —</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}">
                                {{ $product->name }}
                                @if ($product->code) ({{ $product->code }}) @endif
                            </option>
                        @endforeach
                    </select>
                </label>
                <button type="button" class="saas-btn-secondary shrink-0 text-sm" wire:click="addFromCatalog">افزودن</button>
                <button type="button" class="saas-btn-secondary shrink-0 text-sm" wire:click="addManualLine">ردیف دستی</button>
            </div>
        @endif

        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="saas-table min-w-full">
                <thead>
                    <tr>
                        <th>شرح</th>
                        <th class="w-24">تعداد</th>
                        <th class="w-28">واحد</th>
                        <th class="w-36">فی</th>
                        <th class="w-40">مبلغ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lines as $index => $line)
                        @php
                            $qty = is_numeric(str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹',','], ['0','1','2','3','4','5','6','7','8','9',''], $line['quantity'] ?? '1'))
                                ? (float) str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹',','], ['0','1','2','3','4','5','6','7','8','9',''], $line['quantity'] ?? '1')
                                : 1;
                            $price = is_numeric(str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹',','], ['0','1','2','3','4','5','6','7','8','9',''], $line['unit_price_rials'] ?? '0'))
                                ? (int) round((float) str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹',','], ['0','1','2','3','4','5','6','7','8','9',''], $line['unit_price_rials'] ?? '0'))
                                : 0;
                            $lineAmount = (int) round($qty * $price);
                        @endphp
                        <tr wire:key="{{ $line['key'] }}">
                            <td>
                                <input wire:model.live="lines.{{ $index }}.description" class="saas-input" @disabled(! $editable)>
                                @error('lines.'.$index.'.description') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </td>
                            <td>
                                <input wire:model.live="lines.{{ $index }}.quantity" class="saas-input" inputmode="decimal" @disabled(! $editable)>
                            </td>
                            <td>
                                <input wire:model="lines.{{ $index }}.unit_label" class="saas-input" @disabled(! $editable)>
                            </td>
                            <td>
                                <input wire:model.live="lines.{{ $index }}.unit_price_rials" class="saas-input" inputmode="numeric" @disabled(! $editable)>
                            </td>
                            <td>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="whitespace-nowrap text-sm text-zinc-700 dark:text-zinc-200">{{ InvoiceMoney::formatDigits($lineAmount) }}</span>
                                    @if ($editable)
                                        <button type="button" class="rounded-lg p-1 text-zinc-400 hover:bg-rose-50 hover:text-rose-600" wire:click="removeLine({{ $index }})" aria-label="حذف ردیف">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-zinc-600">
                جمع {{ InvoiceMoney::formatDigits($totals['subtotal_rials']) }}
                مالیات {{ InvoiceMoney::formatDigits($totals['tax_rials']) }}
            </p>
            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                نهایی: {{ InvoiceMoney::formatRials($totals['total_rials']) }}
            </p>
        </div>

        @if ($editable)
            <label class="inline-flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                <input type="checkbox" wire:model="notifySms" class="rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500">
                هنگام صدور، مشتری با پیامک مطلع شود
            </label>
        @endif
    </section>

    {{-- Document preview (shared with print) --}}
    <section id="invoice-print-area" class="saas-card mx-auto max-w-4xl space-y-6 p-6 print:max-w-none print:border-0 print:shadow-none">
        <div class="flex items-start justify-between gap-4 border-b border-zinc-200 pb-4 dark:border-zinc-700">
            <div>
                <p class="text-xs font-medium tracking-wide text-zinc-500">
                    {{ $invoice->document_type->englishLabel() }} · {{ $invoice->status->label() }}
                </p>
                <h2 class="mt-1 text-2xl font-bold text-zinc-900 dark:text-zinc-50">{{ $invoice->document_type->label() }}</h2>
            </div>
            <div class="rounded-lg border border-zinc-200 px-3 py-2 text-sm text-zinc-700 dark:border-zinc-700 dark:text-zinc-300">
                <div class="flex justify-between gap-6">
                    <span class="text-zinc-500">شماره</span>
                    <span class="font-medium">{{ $invoice->displayNumber() }}</span>
                </div>
                <div class="mt-1 flex justify-between gap-6">
                    <span class="text-zinc-500">تاریخ</span>
                    <span class="font-medium">{{ JalaliDate::date($invoice->issued_at ?? $invoice->created_at) }}</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 divide-x divide-x-reverse divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            <div class="p-4">
                <p class="text-xs font-semibold text-zinc-500">فروشنده</p>
                <p class="mt-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $sellerName !== '' ? $sellerName : '—' }}</p>
            </div>
            <div class="p-4">
                <p class="text-xs font-semibold text-zinc-500">خریدار</p>
                <p class="mt-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $buyerName !== '' ? $buyerName : ($buyerCompany !== '' ? $buyerCompany : '—') }}</p>
                @if ($buyerCompany !== '' && $buyerName !== '')
                    <p class="mt-1 text-sm text-zinc-600">{{ $buyerCompany }}</p>
                @endif
                @if ($buyerPhone !== '')
                    <p class="mt-1 text-sm text-zinc-600">{{ $buyerPhone }}</p>
                @endif
                @if ($buyerNationalId !== '')
                    <p class="mt-1 text-sm text-zinc-600">شناسه: {{ $buyerNationalId }}</p>
                @endif
                @if ($buyerAddress !== '')
                    <p class="mt-1 text-sm text-zinc-600">{{ $buyerAddress }}</p>
                @endif
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 text-zinc-600 dark:bg-zinc-900/60 dark:text-zinc-300">
                    <tr>
                        <th class="px-3 py-2.5 text-start font-medium">ردیف</th>
                        <th class="px-3 py-2.5 text-start font-medium">شرح کالا / خدمت</th>
                        <th class="px-3 py-2.5 text-start font-medium">واحد</th>
                        <th class="px-3 py-2.5 text-start font-medium">تعداد</th>
                        <th class="px-3 py-2.5 text-start font-medium">فی</th>
                        <th class="px-3 py-2.5 text-start font-medium">مبلغ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lines as $index => $line)
                        @php
                            $qty = is_numeric(str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹',','], ['0','1','2','3','4','5','6','7','8','9',''], $line['quantity'] ?? '1'))
                                ? (float) str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹',','], ['0','1','2','3','4','5','6','7','8','9',''], $line['quantity'] ?? '1')
                                : 1;
                            $price = is_numeric(str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹',','], ['0','1','2','3','4','5','6','7','8','9',''], $line['unit_price_rials'] ?? '0'))
                                ? (int) round((float) str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹',','], ['0','1','2','3','4','5','6','7','8','9',''], $line['unit_price_rials'] ?? '0'))
                                : 0;
                        @endphp
                        <tr class="border-t border-zinc-100 dark:border-zinc-800">
                            <td class="px-3 py-2.5">{{ PersianNumber::format($index + 1) ?? ($index + 1) }}</td>
                            <td class="px-3 py-2.5">{{ $line['description'] !== '' ? $line['description'] : '—' }}</td>
                            <td class="px-3 py-2.5">{{ $line['unit_label'] !== '' ? $line['unit_label'] : '—' }}</td>
                            <td class="px-3 py-2.5">{{ PersianNumber::format($qty) ?? $qty }}</td>
                            <td class="px-3 py-2.5">{{ InvoiceMoney::formatDigits($price) }}</td>
                            <td class="px-3 py-2.5">{{ InvoiceMoney::formatDigits((int) round($qty * $price)) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-8 text-center text-zinc-500">ردیفی ثبت نشده است.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="text-sm text-zinc-500">
                <p>مبالغ به ریال است.</p>
                @if ($notes !== '')
                    <p class="mt-2 text-zinc-700 dark:text-zinc-300">یادداشت: {{ $notes }}</p>
                @endif
            </div>
            <div class="w-full max-w-xs overflow-hidden rounded-xl border border-zinc-200 text-sm dark:border-zinc-700">
                <div class="flex justify-between px-3 py-2">
                    <span class="text-zinc-500">جمع جزء</span>
                    <span>{{ InvoiceMoney::formatDigits($totals['subtotal_rials']) }}</span>
                </div>
                <div class="flex justify-between border-t border-zinc-100 px-3 py-2 dark:border-zinc-800">
                    <span class="text-zinc-500">مالیات ({{ PersianNumber::format((float) $taxPercent) ?? $taxPercent }}٪)</span>
                    <span>{{ InvoiceMoney::formatDigits($totals['tax_rials']) }}</span>
                </div>
                <div class="flex justify-between bg-zinc-900 px-3 py-2.5 font-semibold text-white dark:bg-zinc-100 dark:text-zinc-900">
                    <span>مبلغ قابل پرداخت</span>
                    <span>{{ InvoiceMoney::formatRials($totals['total_rials']) }}</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 pt-2">
            <div>
                <p class="mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">مهر فروشنده</p>
                <div class="flex h-24 items-center justify-center rounded-xl border border-dashed border-zinc-300 text-sm text-zinc-400 dark:border-zinc-600">محل مهر</div>
            </div>
            <div>
                <p class="mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">امضای فروشنده</p>
                <div class="flex h-24 items-center justify-center rounded-xl border border-dashed border-zinc-300 text-sm text-zinc-400 dark:border-zinc-600">محل امضا</div>
            </div>
        </div>
    </section>

    <style>
        @media print {
            body * { visibility: hidden !important; }
            #invoice-print-area, #invoice-print-area * { visibility: visible !important; }
            #invoice-print-area {
                position: absolute;
                inset-inline-start: 0;
                top: 0;
                width: 100%;
                box-shadow: none !important;
                border: none !important;
            }
            .print\:hidden { display: none !important; }
        }
    </style>
</div>
