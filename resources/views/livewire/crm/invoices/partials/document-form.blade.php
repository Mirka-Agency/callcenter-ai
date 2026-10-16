@php
    use App\Application\Crm\Invoices\InvoiceMoney;
@endphp

<div class="space-y-4">
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
            <span class="mb-1 block text-zinc-600">موضوع / یادداشت</span>
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
            · مالیات {{ InvoiceMoney::formatDigits($totals['tax_rials']) }}
        </p>
        <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
            نهایی: {{ InvoiceMoney::formatRials($totals['total_rials']) }}
        </p>
    </div>

    @if ($editable)
        <label class="inline-flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
            <input type="checkbox" wire:model="notifySms" class="rounded border-zinc-300 text-brand focus:ring-brand">
            هنگام صدور، مشتری با پیامک مطلع شود
        </label>

        <div class="flex flex-wrap items-center gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-800">
            <button type="button" class="saas-btn-brand text-sm" wire:click="save" wire:loading.attr="disabled">ذخیره تغییرات</button>
            <button type="button" class="saas-btn-secondary text-sm" wire:click="issue" wire:loading.attr="disabled" wire:confirm="سند صادر شود؟ پس از صدور قابل ویرایش نیست.">صدور سند</button>
            <button type="button" class="text-sm font-medium text-zinc-500 hover:text-zinc-800" wire:click="stopEditing">انصراف از ویرایش</button>
        </div>
    @endif
</div>
