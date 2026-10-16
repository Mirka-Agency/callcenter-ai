@php
    use App\Support\AnalysisInsightPresenter;
    use App\Support\CustomerCompanyPresenter;
    use App\Support\JalaliDate;

    $companyShowRoute = $portal === 'employee' ? 'employee.customers.companies.show' : 'employer.customers.companies.show';
    $companyEditRoute = $portal === 'employee' ? 'employee.customers.companies.edit' : 'employer.customers.companies.edit';
@endphp

<div class="overflow-x-auto" data-tour="customers-companies-table">
    <table class="saas-table crm-directory-table min-w-[860px]">
        <thead>
            <tr>
                <th scope="col">نام</th>
                <th scope="col">صنعت</th>
                <th scope="col">اشخاص</th>
                <th scope="col">شماره تماس</th>
                <th scope="col">کیفیت لید</th>
                <th scope="col">آخرین فعالیت</th>
                <th scope="col" class="w-12"><span class="sr-only">عملیات</span></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($companies as $company)
                @php
                    $showUrl = route($companyShowRoute, $company);
                @endphp
                <tr
                    wire:key="company-row-{{ $company->id }}"
                    data-row-href="{{ $showUrl }}"
                    role="link"
                    tabindex="0"
                    aria-label="مشاهده {{ $company->displayName() }}"
                    class="crm-directory-row"
                >
                    <td>
                        <div class="flex min-w-0 items-center gap-3">
                            <x-saas.avatar :name="$company->displayName()" size="sm" class="!rounded-full text-[0.65rem]" />
                            <span class="truncate font-medium text-zinc-900 dark:text-white">{{ $company->displayName() }}</span>
                        </div>
                    </td>
                    <td class="text-zinc-600 dark:text-zinc-300">
                        <span class="line-clamp-1">{{ $company->industry ?: '—' }}</span>
                    </td>
                    <td class="tabular-nums text-zinc-700 dark:text-zinc-200">
                        {{ JalaliDate::persianDigits(number_format((int) ($company->contacts_count ?? 0))) }}
                    </td>
                    <td class="whitespace-nowrap text-zinc-700 dark:text-zinc-200" dir="ltr">
                        {{ $company->phone ? JalaliDate::persianDigits($company->phone) : '—' }}
                    </td>
                    <td>
                        @if ($company->latest_lead_level)
                            <span @class(['crm-directory-status', CustomerCompanyPresenter::leadBadgeClass($company->latest_lead_level)])>
                                لید {{ AnalysisInsightPresenter::leadLevelLabel($company->latest_lead_level) }}
                            </span>
                        @else
                            <span class="text-zinc-400">—</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap text-zinc-500">
                        {{ shamsi($company->last_contact_at, 'ago') }}
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
                                aria-label="عملیات {{ $company->displayName() }}"
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
                                <a href="{{ route($companyEditRoute, $company) }}" wire:navigate role="menuitem" class="crm-directory-menu-item" @click="open = false">ویرایش</a>
                            </div>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
