@php
    $customerIdentity = array_merge([
        'person_name' => '',
        'company_name' => '',
        'phone_number' => '',
        'email' => '',
        'job_title' => '',
        'evidence' => '',
        'confidence' => 0,
    ], $analysis->customer_identity_json ?? []);
    $hasIdentity = (
        $customerIdentity['person_name'] !== ''
        || $customerIdentity['company_name'] !== ''
        || $customerIdentity['email'] !== ''
        || $customerIdentity['job_title'] !== ''
        || $customerIdentity['phone_number'] !== ''
        || $customerIdentity['evidence'] !== ''
    );
    $confidence = min(100, max(0, (float) $customerIdentity['confidence'] * 100));

    $linkedCustomer = $analysis->call?->customer
        ?? (isset($call) ? $call->customer : null)
        ?? (isset($upload) ? $upload->customer : null);
    $linkedCompany = $linkedCustomer?->company;
    $isEmployeePortal = request()->routeIs('employee.*');
    $customerShowRoute = $linkedCustomer
        ? route($isEmployeePortal ? 'employee.customers.show' : 'employer.customers.show', $linkedCustomer)
        : null;
    $companyShowRoute = $linkedCompany
        ? route($isEmployeePortal ? 'employee.customers.companies.show' : 'employer.customers.companies.show', $linkedCompany)
        : null;
    $identityLinkClass = 'font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300';
@endphp

@if ($hasIdentity)
    <div class="saas-card">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500">هویت مشتری</h2>
        <dl class="mt-4 space-y-3 text-sm">
            @foreach ([
                'person_name' => 'نام',
                'company_name' => 'شرکت',
                'phone_number' => 'تلفن',
                'email' => 'ایمیل',
                'job_title' => 'سمت',
            ] as $field => $label)
                @if (filled($customerIdentity[$field]))
                    @php
                        $fieldHref = match ($field) {
                            'person_name' => $customerShowRoute,
                            'company_name' => $companyShowRoute,
                            default => null,
                        };
                    @endphp
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-zinc-500">{{ $label }}</dt>
                        <dd class="text-end font-medium text-zinc-900 dark:text-white">
                            @if ($fieldHref)
                                <a href="{{ $fieldHref }}" class="{{ $identityLinkClass }}" wire:navigate>{{ $customerIdentity[$field] }}</a>
                            @else
                                {{ $customerIdentity[$field] }}
                            @endif
                        </dd>
                    </div>
                @endif
            @endforeach
            @if ($confidence > 0)
                <div class="border-t border-zinc-200/80 pt-3 dark:border-zinc-800">
                    <div class="flex items-center justify-between gap-3 text-xs">
                        <span class="text-zinc-500">اطمینان استخراج</span>
                        <span class="font-medium tabular-nums">{{ number_format($confidence, 0) }}٪</span>
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <div class="h-1.5 rounded-full bg-indigo-500" style="width: {{ $confidence }}%"></div>
                    </div>
                </div>
            @endif
        </dl>
        @if (filled($customerIdentity['evidence']))
            <blockquote class="mt-4 rounded-lg border-s-2 border-indigo-300 bg-indigo-50/50 px-3 py-2 text-xs leading-relaxed text-zinc-600 dark:border-indigo-500/40 dark:bg-indigo-950/20 dark:text-zinc-300">
                «{{ $customerIdentity['evidence'] }}»
            </blockquote>
        @endif
    </div>
@endif
