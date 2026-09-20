@php
    $companyShowRoute = $portal === 'employee' ? 'employee.customers.companies.show' : 'employer.customers.companies.show';
    $companyListRoute = $portal === 'employee' ? 'employee.customers.companies.index' : 'employer.customers.companies.index';
    $contactShowRoute = $portal === 'employee' ? 'employee.customers.show' : 'employer.customers.show';
    $contactListRoute = $portal === 'employee' ? 'employee.customers.contacts.index' : 'employer.customers.contacts.index';
    $companyCreateRoute = $portal === 'employee' ? 'employee.customers.companies.create' : 'employer.customers.companies.create';
    $contactCreateRoute = $portal === 'employee' ? 'employee.customers.contacts.create' : 'employer.customers.contacts.create';
@endphp

<div class="saas-page space-y-8">
    <div class="flex flex-wrap items-center gap-3" data-tour="customers-hub-actions">
        <a
            href="{{ route($companyCreateRoute) }}"
            class="saas-btn-primary text-sm"
            wire:navigate
        >شرکت جدید</a>
        <a
            href="{{ route($contactCreateRoute) }}"
            class="saas-btn border border-zinc-900 bg-white text-zinc-900 hover:bg-zinc-50 dark:border-white dark:bg-transparent dark:text-white dark:hover:bg-zinc-800"
            wire:navigate
        >شخص جدید</a>
    </div>

    <section class="saas-hero saas-hero--accent" data-tour="customers-hub-hero">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-medium uppercase tracking-wider text-indigo-600 dark:text-indigo-400">پایگاه مشتریان</p>
                <h1 class="text-3xl font-bold tracking-tight">شرکت‌ها و اشخاص</h1>
                <p class="mt-2 max-w-2xl text-sm leading-7 text-zinc-600 dark:text-zinc-300">
                    شرکت‌ها و اشخاص را جداگانه مدیریت کنید — هر بخش لیست و جستجوی مخصوص خودش را دارد.
                </p>
            </div>
            <div class="flex w-fit shrink-0 gap-2 self-end lg:self-auto" data-tour="customers-hub-stats">
                <div class="min-w-[4.75rem] rounded-xl border border-white/60 bg-white/70 px-3 py-2 text-center shadow-sm dark:border-zinc-700/50 dark:bg-zinc-900/60">
                    <p class="text-xs text-zinc-500">شرکت</p>
                    <p class="text-lg font-bold tabular-nums text-indigo-600 dark:text-indigo-400">{{ number_format($stats['companies']) }}</p>
                </div>
                <div class="min-w-[4.75rem] rounded-xl border border-white/60 bg-white/70 px-3 py-2 text-center shadow-sm dark:border-zinc-700/50 dark:bg-zinc-900/60">
                    <p class="text-xs text-zinc-500">شخص</p>
                    <p class="text-lg font-bold tabular-nums text-violet-600 dark:text-violet-400">{{ number_format($stats['contacts']) }}</p>
                </div>
                <div class="min-w-[4.75rem] rounded-xl border border-white/60 bg-white/70 px-3 py-2 text-center shadow-sm dark:border-zinc-700/50 dark:bg-zinc-900/60">
                    <p class="text-xs text-zinc-500">تماس</p>
                    <p class="text-lg font-bold tabular-nums text-sky-600 dark:text-sky-400">{{ number_format($stats['calls']) }}</p>
                </div>
            </div>
        </div>
    </section>

    <div class="grid gap-5 md:grid-cols-2" data-tour="customers-hub-cards">
        <a
            href="{{ route($companyListRoute) }}"
            wire:navigate
            data-tour="customers-hub-companies"
            class="group relative overflow-hidden rounded-2xl border border-indigo-200/70 bg-gradient-to-br from-indigo-50 via-white to-violet-50 p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-lg dark:border-indigo-500/30 dark:from-indigo-950/40 dark:via-zinc-900 dark:to-violet-950/30"
        >
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-zinc-900 dark:text-white">شرکت‌ها</h2>
                    <p class="mt-2 text-sm leading-7 text-zinc-600 dark:text-zinc-300">
                        شرکت‌های مشتری — با چند شخص، آمار تجمیعی و تاریخچه تماس.
                    </p>
                    <p class="mt-4 text-sm font-semibold text-indigo-600 group-hover:underline dark:text-indigo-400">
                        ورود به لیست شرکت‌ها ({{ number_format($stats['companies']) }})
                    </p>
                </div>
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-indigo-600 text-white shadow-lg shadow-indigo-500/30" aria-hidden="true">
                    <x-saas.icon name="building" class="h-7 w-7" />
                </div>
            </div>
        </a>

        <a
            href="{{ route($contactListRoute) }}"
            wire:navigate
            data-tour="customers-hub-contacts"
            class="group relative overflow-hidden rounded-2xl border border-violet-200/70 bg-gradient-to-br from-violet-50 via-white to-fuchsia-50 p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-violet-300 hover:shadow-lg dark:border-violet-500/30 dark:from-violet-950/40 dark:via-zinc-900 dark:to-fuchsia-950/30"
        >
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-zinc-900 dark:text-white">اشخاص</h2>
                    <p class="mt-2 text-sm leading-7 text-zinc-600 dark:text-zinc-300">
                        افراد حقیقی — پروفایل تماس، امتیاز لید و اتصال به شرکت مربوطه.
                    </p>
                    <p class="mt-4 text-sm font-semibold text-violet-600 group-hover:underline dark:text-violet-400">
                        ورود به لیست اشخاص ({{ number_format($stats['contacts']) }})
                    </p>
                </div>
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-violet-600 text-white shadow-lg shadow-violet-500/30" aria-hidden="true">
                    <x-saas.icon name="user" class="h-7 w-7" />
                </div>
            </div>
        </a>
    </div>

    @if ($recentCompanies->isNotEmpty() || $recentContacts->isNotEmpty())
        <div class="grid gap-8 lg:grid-cols-2" data-tour="customers-hub-recent">
            @if ($recentCompanies->isNotEmpty())
                <section class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold">شرکت‌های اخیر</h3>
                        <a href="{{ route($companyListRoute) }}" class="text-sm font-medium text-indigo-600 hover:underline" wire:navigate>همه</a>
                    </div>
                    <div class="space-y-3">
                        @foreach ($recentCompanies as $company)
                            <a
                                href="{{ route($companyShowRoute, $company) }}"
                                wire:navigate
                                class="flex items-center justify-between rounded-xl border border-zinc-200/80 bg-white px-4 py-3 transition hover:border-indigo-300 dark:border-zinc-800 dark:bg-zinc-900"
                            >
                                <span class="font-medium">{{ $company->displayName() }}</span>
                                <span class="text-xs text-zinc-500">{{ $company->contacts_count }} شخص</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($recentContacts->isNotEmpty())
                <section class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold">اشخاص اخیر</h3>
                        <a href="{{ route($contactListRoute) }}" class="text-sm font-medium text-violet-600 hover:underline" wire:navigate>همه</a>
                    </div>
                    <div class="space-y-3">
                        @foreach ($recentContacts as $contact)
                            <a
                                href="{{ route($contactShowRoute, $contact) }}"
                                wire:navigate
                                class="flex items-center justify-between gap-3 rounded-xl border border-zinc-200/80 bg-white px-4 py-3 transition hover:border-violet-300 dark:border-zinc-800 dark:bg-zinc-900"
                            >
                                <div class="min-w-0">
                                    <p class="truncate font-medium">{{ $contact->displayName() }}</p>
                                    <p class="truncate text-xs text-zinc-500">{{ $contact->companyLabel() ?: 'بدون شرکت' }}</p>
                                </div>
                                @if ($contact->latest_lead_score)
                                    <span class="shrink-0 text-sm font-semibold text-indigo-600">{{ $contact->latest_lead_score }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    @endif
</div>
