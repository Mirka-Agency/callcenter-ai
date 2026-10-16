@php
    use App\Application\Crm\Invoices\InvoiceMoney;
    use App\Support\JalaliDate;
    use App\Support\PersianNumber;
@endphp

<section id="invoice-print-area" class="crm-document-preview space-y-6">
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

    <div class="grid grid-cols-2 gap-4 pt-2 print:break-inside-avoid">
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
