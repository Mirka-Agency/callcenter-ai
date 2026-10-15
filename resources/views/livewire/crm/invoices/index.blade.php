@php
    use App\Application\Crm\Invoices\InvoiceMoney;
    use App\Support\JalaliDate;
@endphp

<div class="saas-page space-y-5">
    <x-saas.page-header
        title="فاکتور و پیش فاکتور"
        description="اسناد فروش سازمان؛ هنگام صدور می‌توانید مشتری را با پیامک مطلع کنید."
    >
        <x-slot:actions>
            @if ($canManage)
                <button type="button" class="saas-btn-primary text-sm" wire:click="openCreate">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                    </svg>
                    سند جدید
                </button>
            @endif
        </x-slot:actions>
    </x-saas.page-header>

    <div class="flex flex-wrap items-center gap-2" role="toolbar" aria-label="فیلتر نوع سند">
        @foreach (['all' => 'همه', 'proforma' => 'پیش‌فاکتور', 'invoice' => 'فاکتور'] as $value => $label)
            <button
                type="button"
                wire:click="setType('{{ $value }}')"
                @class([
                    'inline-flex items-center rounded-lg border px-3.5 py-1.5 text-sm transition',
                    'border-indigo-600 bg-indigo-600 font-semibold text-white' => $type === $value,
                    'border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300' => $type !== $value,
                ])
            >{{ $label }}</button>
        @endforeach
    </div>

    <section class="saas-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="saas-table min-w-full">
                <thead>
                    <tr>
                        <th>شماره</th>
                        <th>نوع</th>
                        <th>وضعیت</th>
                        <th>خریدار</th>
                        <th>مبلغ</th>
                        <th>به‌روزرسانی</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $document)
                        <tr wire:key="invoice-{{ $document->id }}" class="hover:bg-zinc-50/80 dark:hover:bg-zinc-900/50">
                            <td class="whitespace-nowrap font-medium text-zinc-900 dark:text-zinc-100">
                                <a href="{{ route($portal.'.crm.invoices.show', $document) }}" wire:navigate class="text-indigo-700 hover:underline dark:text-indigo-300">
                                    {{ $document->displayNumber() }}
                                </a>
                            </td>
                            <td class="text-sm text-zinc-700 dark:text-zinc-300">
                                <a href="{{ route($portal.'.crm.invoices.show', $document) }}" wire:navigate class="block">
                                    {{ $document->document_type->label() }}
                                </a>
                            </td>
                            <td>
                                <a href="{{ route($portal.'.crm.invoices.show', $document) }}" wire:navigate class="inline-flex">
                                    <span @class([
                                        'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                                        'bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300' => $document->isDraft(),
                                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' => $document->isIssued(),
                                    ])>
                                        {{ $document->status->label() }}
                                    </span>
                                </a>
                            </td>
                            <td class="max-w-xs truncate text-sm text-zinc-700 dark:text-zinc-300">
                                <a href="{{ route($portal.'.crm.invoices.show', $document) }}" wire:navigate class="block truncate">
                                    {{ $document->buyerDisplayName() }}
                                </a>
                            </td>
                            <td class="whitespace-nowrap text-sm text-zinc-800 dark:text-zinc-200">
                                <a href="{{ route($portal.'.crm.invoices.show', $document) }}" wire:navigate class="block">
                                    {{ InvoiceMoney::formatRials((int) $document->total_rials) }}
                                </a>
                            </td>
                            <td class="whitespace-nowrap text-sm text-zinc-500">
                                <a href="{{ route($portal.'.crm.invoices.show', $document) }}" wire:navigate class="block">
                                    {{ JalaliDate::datetime($document->updated_at) }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-16 text-center text-sm text-zinc-500">
                                هنوز سندی نیست.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($invoices->hasPages())
        <div>{{ $invoices->links() }}</div>
    @endif

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
                                        'border-indigo-600 bg-indigo-600 text-white' => $documentType === $value,
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
                    <button type="submit" class="saas-btn-primary text-sm" wire:loading.attr="disabled">ایجاد پیش‌نویس</button>
                </div>
            </form>
        </div>
    @endif
</div>
