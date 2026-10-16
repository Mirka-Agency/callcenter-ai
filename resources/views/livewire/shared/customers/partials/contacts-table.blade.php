@php
    use App\Domain\Customer\CustomerLifecycleStage;
    use App\Support\CustomerPresenter;
    use App\Support\JalaliDate;

    $contactShowRoute = $portal === 'employee' ? 'employee.customers.show' : 'employer.customers.show';
    $contactEditRoute = $portal === 'employee' ? 'employee.customers.edit' : 'employer.customers.edit';
@endphp

<div class="overflow-x-auto" data-tour="customers-contacts-table">
    <table class="saas-table crm-directory-table min-w-[960px]">
        <thead>
            <tr>
                <th scope="col">نام</th>
                <th scope="col">شرکت</th>
                <th scope="col">سمت</th>
                <th scope="col">شماره تماس</th>
                <th scope="col">وضعیت</th>
                <th scope="col">برچسب‌ها</th>
                <th scope="col">آخرین فعالیت</th>
                <th scope="col" class="w-12"><span class="sr-only">عملیات</span></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($contacts as $customer)
                @php
                    $showUrl = route($contactShowRoute, $customer);
                    $stage = $customer->lifecycle_stage instanceof CustomerLifecycleStage
                        ? $customer->lifecycle_stage
                        : null;
                    $tags = CustomerPresenter::directoryTags($customer);
                @endphp
                <tr
                    wire:key="contact-row-{{ $customer->id }}"
                    data-row-href="{{ $showUrl }}"
                    role="link"
                    tabindex="0"
                    aria-label="مشاهده {{ $customer->displayName() }}"
                    class="crm-directory-row"
                >
                    <td>
                        <div class="flex min-w-0 items-center gap-3">
                            <x-saas.avatar :name="$customer->displayName()" size="sm" class="!rounded-full text-[0.65rem]" />
                            <span class="truncate font-medium text-zinc-900 dark:text-white">{{ $customer->displayName() }}</span>
                        </div>
                    </td>
                    <td class="text-zinc-600 dark:text-zinc-300">
                        <span class="line-clamp-1">{{ $customer->companyLabel() ?: '—' }}</span>
                    </td>
                    <td class="text-zinc-600 dark:text-zinc-300">
                        <span class="line-clamp-1">{{ $customer->job_title ?: '—' }}</span>
                    </td>
                    <td class="whitespace-nowrap text-zinc-700 dark:text-zinc-200" dir="ltr">
                        {{ $customer->phone_number ? JalaliDate::persianDigits($customer->phone_number) : '—' }}
                    </td>
                    <td>
                        @if ($stage)
                            <span @class(['crm-directory-status', $stage->badgeClass()])>{{ $stage->label() }}</span>
                        @else
                            <span @class(['crm-directory-status', CustomerLifecycleStage::unknownBadgeClass()])>
                                {{ CustomerLifecycleStage::unknownLabel() }}
                            </span>
                        @endif
                    </td>
                    <td>
                        @if ($tags === [])
                            <span class="text-zinc-400">—</span>
                        @else
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($tags as $tag)
                                    <span @class(['crm-directory-tag', $tag['class']])>{{ $tag['label'] }}</span>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td class="whitespace-nowrap text-zinc-500">
                        {{ shamsi($customer->last_contact_at, 'ago') }}
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
                                aria-label="عملیات {{ $customer->displayName() }}"
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
                                <a href="{{ route($contactEditRoute, $customer) }}" wire:navigate role="menuitem" class="crm-directory-menu-item" @click="open = false">ویرایش</a>
                            </div>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
