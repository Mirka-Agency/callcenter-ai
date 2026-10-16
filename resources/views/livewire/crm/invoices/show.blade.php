@php
    use App\Application\Crm\Invoices\InvoiceMoney;
    use App\Domain\Crm\Invoices\InvoiceDocumentType;
    use App\Domain\Crm\Invoices\InvoiceStatus;
    use App\Support\JalaliDate;

    $documentTitle = $invoice->displayTitle();
    $createdLabel = JalaliDate::persianDigits(JalaliDate::format($invoice->created_at, 'j F Y، H:i'));
    $companyName = filled($buyerCompany)
        ? $buyerCompany
        : ($linkedCustomer?->companyLabel() ?: null);
    $hasMeaningfulLines = collect($lines)->contains(function (array $line): bool {
        $description = trim((string) ($line['description'] ?? ''));
        $price = trim((string) ($line['unit_price_rials'] ?? ''));

        return $description !== '' && $description !== 'قلم جدید' && $price !== '' && $price !== '0';
    });
@endphp

<div class="saas-page crm-document-show space-y-5" x-data>
    <nav class="crm-document-show__breadcrumbs" aria-label="مسیر">
        <a href="{{ route($portal.'.crm.invoices.index') }}" wire:navigate>اسناد</a>
        <span aria-hidden="true">›</span>
        <span>{{ $documentTitle }}</span>
    </nav>

    <header class="crm-document-show__header">
        <div class="crm-document-show__heading">
            <span class="crm-document-show__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 3h6l4 4v12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 3v4h4M9 12h6M9 16h4" />
                </svg>
            </span>
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h1>{{ $documentTitle }}</h1>
                    <span class="crm-document-show__id">#{{ JalaliDate::persianDigits($invoice->id) }}</span>
                </div>
                <p>
                    توسط {{ $creatorName }}
                    <span aria-hidden="true">•</span>
                    ایجاد شده در {{ $createdLabel }}
                </p>
            </div>
        </div>

        @if ($canManage)
            <div class="crm-document-show__actions">
                <div
                    class="relative"
                    x-data="{ open: false }"
                    @click.outside="open = false"
                    @keydown.escape.window="open = false"
                >
                    <button
                        type="button"
                        @class([
                            'crm-document-show__status-btn',
                            'is-draft' => $invoice->isDraft(),
                            'is-issued' => $invoice->isIssued(),
                        ])
                        @click="open = !open"
                        :aria-expanded="open"
                        aria-haspopup="menu"
                    >
                        {{ $invoice->status->label() }}
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                        </svg>
                    </button>
                    <div x-cloak x-show="open" x-transition.opacity class="crm-document-show__menu" role="menu">
                        @if ($invoice->isDraft())
                            <button type="button" role="menuitem" class="crm-document-show__menu-item" wire:click="issue" wire:confirm="سند صادر شود؟ پس از صدور قابل ویرایش نیست." @click="open = false">
                                صدور سند
                            </button>
                        @else
                            <span class="crm-document-show__menu-item is-muted">سند صادر شده است</span>
                        @endif
                    </div>
                </div>

                @if ($invoice->isDraft())
                    <button type="button" class="saas-btn-secondary text-sm" wire:click="startEditing">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3l1 1a2.1 2.1 0 0 1 0 3L8 20l-4 1 1-4Z" />
                        </svg>
                        ویرایش
                    </button>
                @endif

                <div
                    class="relative"
                    x-data="{ open: false }"
                    @click.outside="open = false"
                    @keydown.escape.window="open = false"
                >
                    <button
                        type="button"
                        class="crm-directory-menu-btn"
                        @click="open = !open"
                        :aria-expanded="open"
                        aria-haspopup="true"
                        aria-label="عملیات بیشتر"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <circle cx="12" cy="5" r="1.75" />
                            <circle cx="12" cy="12" r="1.75" />
                            <circle cx="12" cy="19" r="1.75" />
                        </svg>
                    </button>
                    <div x-cloak x-show="open" x-transition.opacity class="crm-document-show__menu" role="menu">
                        <button type="button" role="menuitem" class="crm-document-show__menu-item" onclick="window.print()" @click="open = false">چاپ</button>
                        <button type="button" role="menuitem" class="crm-document-show__menu-item is-danger" wire:click="remove" wire:confirm="این سند حذف شود؟" @click="open = false">حذف</button>
                    </div>
                </div>
            </div>
        @endif
    </header>

    <div class="crm-document-stepper" role="list" aria-label="مراحل سند">
        @foreach ($lifecycleSteps as $step)
            <div
                role="listitem"
                @class([
                    'crm-document-step',
                    'is-done' => $step['state'] === 'done',
                    'is-current' => $step['state'] === 'current',
                    'is-upcoming' => $step['state'] === 'upcoming',
                ])
            >
                <span>{{ $step['label'] }}</span>
            </div>
        @endforeach
    </div>

    @error('status') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
    @error('lines') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror

    <div class="crm-document-show__layout">
        <div class="crm-document-show__main space-y-4">
            @if ($invoice->isDraft() && ! $hasMeaningfulLines)
                <section class="crm-document-empty-card">
                    <div class="crm-document-empty-card__visual" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                            <rect x="4" y="5" width="16" height="15" rx="2" />
                            <path stroke-linecap="round" d="M8 3v4M16 3v4M4 11h16" />
                            <circle cx="16.5" cy="16.5" r="3.5" />
                            <path stroke-linecap="round" d="M16.5 15v3M15 16.5h3" />
                        </svg>
                    </div>
                    <div>
                        <h2>هنوز ردیف کالا یا خدمت ندارید</h2>
                        <p>برای تکمیل {{ $invoice->document_type->label() }}، اقلام را از کاتالوگ یا به‌صورت دستی اضافه کنید.</p>
                    </div>
                    @if ($canManage)
                        <button type="button" class="saas-btn-brand text-sm" wire:click="startEditing">
                            + افزودن ردیف
                        </button>
                    @endif
                </section>
            @elseif ($invoice->isDraft() && $hasMeaningfulLines)
                <section class="crm-document-empty-card crm-document-empty-card--ready">
                    <div class="crm-document-empty-card__visual" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 3h6l4 4v12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="m9 13 2 2 4-4" />
                        </svg>
                    </div>
                    <div>
                        <h2>سند آماده صدور است</h2>
                        <p>پس از بررسی ردیف‌ها و مبلغ نهایی، می‌توانید سند را صادر کنید.</p>
                    </div>
                    @if ($canManage)
                        <button type="button" class="saas-btn-brand text-sm" wire:click="issue" wire:confirm="سند صادر شود؟ پس از صدور قابل ویرایش نیست.">
                            صدور سند
                        </button>
                    @endif
                </section>
            @endif

            <section class="saas-card overflow-hidden print:hidden">
                <nav class="crm-document-tabs" aria-label="بخش‌های سند">
                    @foreach ([
                        'document' => 'جزئیات',
                        'preview' => 'پیش‌نمایش',
                        'history' => 'سابقه',
                    ] as $tab => $label)
                        <button
                            type="button"
                            wire:click="setTab('{{ $tab }}')"
                            @class(['crm-document-tab', 'is-active' => $activeTab === $tab])
                            @if ($activeTab === $tab) aria-current="page" @endif
                        >{{ $label }}</button>
                    @endforeach
                </nav>

                <div class="p-4 sm:p-5">
                    @if ($activeTab === 'document')
                        @include('livewire.crm.invoices.partials.document-form')
                    @elseif ($activeTab === 'preview')
                        @include('livewire.crm.invoices.partials.document-preview')
                    @else
                        <div class="crm-document-timeline">
                            @forelse ($history as $entry)
                                <article @class(['crm-document-timeline__item', 'tone-'.$entry['type']])>
                                    <div class="crm-document-timeline__meta">
                                        <span class="crm-document-timeline__label">{{ $entry['label'] }}</span>
                                        <time datetime="{{ optional($entry['at'])->toIso8601String() }}">
                                            {{ $entry['at'] ? JalaliDate::persianDigits(JalaliDate::datetime($entry['at'])) : '—' }}
                                        </time>
                                    </div>
                                    <p>{{ $entry['body'] }}</p>
                                </article>
                            @empty
                                <p class="py-10 text-center text-sm text-zinc-500">سابقه‌ای ثبت نشده است.</p>
                            @endforelse
                        </div>
                    @endif
                </div>
            </section>
        </div>

        <aside class="crm-document-show__sidebar space-y-3 print:hidden">
            <section class="crm-document-side-card">
                <header>
                    <span aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 3h6l4 4v12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" /></svg>
                    </span>
                    <h2>اطلاعات سند</h2>
                </header>
                <dl>
                    <div>
                        <dt>مبلغ</dt>
                        <dd class="crm-document-side-card__amount">{{ InvoiceMoney::formatRials($totals['total_rials']) }}</dd>
                    </div>
                    <div>
                        <dt>وضعیت</dt>
                        <dd>
                            <span @class([
                                'crm-documents-status',
                                'is-draft' => $invoice->status === InvoiceStatus::Draft,
                                'is-issued' => $invoice->status === InvoiceStatus::Issued,
                            ])>{{ $invoice->status->label() }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt>نوع</dt>
                        <dd>
                            <span @class([
                                'crm-documents-type',
                                'is-invoice' => $invoice->document_type === InvoiceDocumentType::Invoice,
                                'is-proforma' => $invoice->document_type === InvoiceDocumentType::Proforma,
                            ])>{{ $invoice->document_type->label() }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt>شماره</dt>
                        <dd>{{ $invoice->displayNumber() }}</dd>
                    </div>
                </dl>
            </section>

            <section class="crm-document-side-card">
                <header>
                    <span aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" /></svg>
                    </span>
                    <h2>شخص مرتبط</h2>
                </header>
                @if (filled($buyerName) || $linkedCustomer)
                    <div class="crm-document-side-card__person">
                        <x-saas.avatar :name="$buyerName !== '' ? $buyerName : ($linkedCustomer?->displayName() ?? 'مخاطب')" size="sm" class="!rounded-full" />
                        <div class="min-w-0">
                            <p class="truncate font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $buyerName !== '' ? $buyerName : ($linkedCustomer?->displayName() ?? '—') }}
                            </p>
                            @if ($linkedCustomer?->job_title)
                                <p class="truncate text-xs text-zinc-500">{{ $linkedCustomer->job_title }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="crm-document-side-card__contacts">
                        @if (filled($buyerPhone) || filled($linkedCustomer?->phone_number))
                            @php $phone = $buyerPhone !== '' ? $buyerPhone : (string) $linkedCustomer?->phone_number; @endphp
                            <a href="tel:{{ $phone }}" class="crm-document-contact-chip" title="تماس">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 4h3l2 5-2 1a11 11 0 0 0 5 5l1-2 5 2v3a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z" /></svg>
                            </a>
                        @endif
                        @if (filled($linkedCustomer?->email))
                            <a href="mailto:{{ $linkedCustomer->email }}" class="crm-document-contact-chip" title="ایمیل">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16v12H4V6Zm0 0 8 7 8-7" /></svg>
                            </a>
                        @endif
                    </div>
                @else
                    <p class="text-sm text-zinc-500">مخاطبی متصل نیست.</p>
                @endif
            </section>

            <section class="crm-document-side-card">
                <header>
                    <span aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6" /></svg>
                    </span>
                    <h2>شرکت مرتبط</h2>
                </header>
                @if ($companyName)
                    <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $companyName }}</p>
                    @if ($linkedCustomer?->company?->industry)
                        <p class="mt-1 text-sm text-zinc-500">{{ $linkedCustomer->company->industry }}</p>
                    @endif
                @else
                    <p class="text-sm text-zinc-500">شرکتی ثبت نشده است.</p>
                @endif
            </section>

            <section class="crm-document-side-card">
                <header>
                    <span aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h10M4 17h7" /></svg>
                    </span>
                    <h2>فیلدهای بیشتر</h2>
                </header>
                <dl>
                    <div>
                        <dt>شناسه ملی</dt>
                        <dd>{{ $buyerNationalId !== '' ? $buyerNationalId : '—' }}</dd>
                    </div>
                    <div>
                        <dt>کد اقتصادی</dt>
                        <dd>{{ $buyerEconomicCode !== '' ? $buyerEconomicCode : '—' }}</dd>
                    </div>
                    <div>
                        <dt>آدرس</dt>
                        <dd>{{ $buyerAddress !== '' ? $buyerAddress : '—' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="crm-document-side-card">
                <header>
                    <span aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 2M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z" /></svg>
                    </span>
                    <h2>نمای سند</h2>
                </header>
                <dl>
                    <div>
                        <dt>تاریخ ایجاد</dt>
                        <dd>{{ JalaliDate::persianDigits(JalaliDate::date($invoice->created_at)) }}</dd>
                    </div>
                    <div>
                        <dt>تاریخ صدور</dt>
                        <dd>{{ $invoice->issued_at ? JalaliDate::persianDigits(JalaliDate::date($invoice->issued_at)) : '—' }}</dd>
                    </div>
                    <div>
                        <dt>جمع جزء</dt>
                        <dd>{{ InvoiceMoney::formatDigits($totals['subtotal_rials']) }}</dd>
                    </div>
                    <div>
                        <dt>مالیات</dt>
                        <dd>{{ InvoiceMoney::formatDigits($totals['tax_rials']) }}</dd>
                    </div>
                </dl>
            </section>
        </aside>
    </div>

    <div class="hidden print:block">
        @include('livewire.crm.invoices.partials.document-preview')
    </div>

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
