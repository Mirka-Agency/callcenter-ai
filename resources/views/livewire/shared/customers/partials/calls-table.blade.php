@php
    use App\Domain\Voip\Enums\CallDirection;
    use App\Domain\Voip\Enums\CallStatus;
    use App\Services\Reports\CallMetricsAnalytics;
    use App\Support\AnalysisCallPresenter;
    use App\Support\JalaliDate;

    $durationFormatter = app(CallMetricsAnalytics::class);
    $contactShowRoute = $portal === 'employee' ? 'employee.customers.show' : 'employer.customers.show';
    $analysisShowRoute = $portal === 'employee' ? 'employee.calls.show' : 'employer.intelligence.show';
@endphp

<div class="overflow-x-auto" data-tour="customers-calls-table">
    <table class="saas-table crm-directory-table min-w-[860px]">
        <thead>
            <tr>
                <th scope="col">شخص</th>
                <th scope="col">شرکت</th>
                <th scope="col">شماره</th>
                <th scope="col">جهت</th>
                <th scope="col">وضعیت</th>
                <th scope="col">مدت</th>
                <th scope="col">زمان</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($calls as $call)
                @php
                    $customer = $call->customer;
                    $analysis = $call->conversationAnalysis;
                    $showUrl = $analysis
                        ? route($analysisShowRoute, $analysis)
                        : ($customer ? route($contactShowRoute, $customer) : null);
                    $direction = CallDirection::tryFrom((string) $call->direction);
                    $status = CallStatus::tryFrom((string) $call->status);
                    $phone = $call->customer_phone ?: $call->caller_number;
                    $occurredAt = $call->occurredAt();
                @endphp
                <tr
                    wire:key="call-row-{{ $call->id }}"
                    @if ($showUrl) data-row-href="{{ $showUrl }}" role="link" tabindex="0" @endif
                    aria-label="مشاهده تماس {{ $customer?->displayName() ?: $phone }}"
                    class="crm-directory-row"
                >
                    <td>
                        <div class="flex min-w-0 items-center gap-3">
                            <x-saas.avatar :name="$customer?->displayName() ?: ($call->customer_name ?: 'تماس')" size="sm" class="!rounded-full text-[0.65rem]" />
                            <span class="truncate font-medium text-zinc-900 dark:text-white">{{ $customer?->displayName() ?: ($call->customer_name ?: '—') }}</span>
                        </div>
                    </td>
                    <td class="text-zinc-600 dark:text-zinc-300">
                        <span class="line-clamp-1">{{ $customer?->companyLabel() ?: '—' }}</span>
                    </td>
                    <td class="whitespace-nowrap text-zinc-700 dark:text-zinc-200" dir="ltr">
                        {{ $phone ? JalaliDate::persianDigits($phone) : '—' }}
                    </td>
                    <td class="text-zinc-600 dark:text-zinc-300">{{ $direction?->label() ?: '—' }}</td>
                    <td>
                        <span @class(['crm-directory-status', AnalysisCallPresenter::statusBadgeClass($status)])>
                            {{ $status?->label() ?: '—' }}
                        </span>
                    </td>
                    <td class="whitespace-nowrap tabular-nums text-zinc-600 dark:text-zinc-300">
                        {{ JalaliDate::persianDigits($durationFormatter->formatDuration((int) ($call->duration_seconds ?? 0))) }}
                    </td>
                    <td class="whitespace-nowrap text-zinc-500">
                        {{ $occurredAt ? shamsi($occurredAt, 'ago') : '—' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
