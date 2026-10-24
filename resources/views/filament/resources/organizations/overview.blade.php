@php
    use App\Support\JalaliDate;
    use App\Support\Money;
    use App\Support\PersianNumber;

    $number = fn (int|float|null $value): string => $value === null ? '—' : PersianNumber::format($value, null, 0);
@endphp

<x-filament-panels::page>
    <div class="grid gap-4 lg:grid-cols-4">
        <x-filament::section>
            <p class="text-sm text-gray-500">{{ __('filament.platform_analytics.health_score') }}</p>
            @if ($health)
                <div class="mt-2 flex items-center gap-2">
                    <span class="text-3xl font-bold">{{ $number($health->score) }}</span>
                    <x-filament::badge :color="$health->color()">{{ $health->label() }}</x-filament::badge>
                </div>
                <p class="mt-1 text-xs text-gray-500">
                    {{ __('filament.platform_analytics.analyses_trend', ['recent' => $number($health->analysesLast30Days), 'previous' => $number($health->analysesPrevious30Days)]) }}
                </p>
            @else
                <p class="mt-2 text-sm text-gray-500">{{ __('admin.organization_overview.health_unavailable') }}</p>
            @endif
        </x-filament::section>

        <x-filament::section>
            <p class="text-sm text-gray-500">{{ __('admin.billing.plan') }}</p>
            <p class="mt-2 text-xl font-bold">{{ $billing['plan'] ?? __('admin.billing.no_subscription') }}</p>
            @if ($billing['status'])
                <x-filament::badge :color="$billing['status']->color()" class="mt-1 inline-flex">{{ $billing['status']->label() }}</x-filament::badge>
            @endif
            @if ($billing['period_ends_at'])
                <p class="mt-1 text-xs text-gray-500">{{ __('admin.billing.renews_on', ['date' => JalaliDate::date($billing['period_ends_at'])]) }}</p>
            @endif
        </x-filament::section>

        <x-filament::section>
            <p class="text-sm text-gray-500">{{ __('admin.billing.outstanding') }}</p>
            <p class="mt-2 text-xl font-bold {{ $billing['overdue_invoices'] > 0 ? 'text-danger-600' : '' }}">{{ Money::format($billing['outstanding_rials']) }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ __('admin.billing.overdue_invoices', ['count' => $number($billing['overdue_invoices'])]) }}</p>
        </x-filament::section>

        <x-filament::section>
            <p class="text-sm text-gray-500">{{ __('admin.billing.quota_usage') }}</p>
            <p class="mt-2 text-xl font-bold">
                {{ $number($billing['usage']) }}
                <span class="text-sm font-normal text-gray-500">/ {{ $billing['quota'] === null ? __('admin.billing.unlimited') : $number($billing['quota']) }}</span>
            </p>
            @if ($billing['quota'])
                <div class="mt-2 h-2 rounded-full bg-gray-100 dark:bg-gray-800">
                    <div class="h-2 rounded-full {{ $billing['usage'] >= $billing['quota'] ? 'bg-danger-500' : 'bg-primary-500' }}" style="width: {{ min(100, round($billing['usage'] / $billing['quota'] * 100)) }}%"></div>
                </div>
            @endif
        </x-filament::section>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-filament::section :heading="__('admin.organization_overview.health_reasons')" class="lg:col-span-1">
            @if ($health && $health->reasons !== [])
                <ul class="space-y-2 text-sm">
                    @foreach ($health->reasonLabels() as $reason)
                        <li class="flex items-start gap-2"><x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5 text-warning-500" /> {{ $reason }}</li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-gray-500">{{ __('admin.organization_overview.no_health_issues') }}</p>
            @endif

            <div class="mt-4 grid grid-cols-2 gap-2 text-sm">
                <div><span class="text-gray-500">{{ __('filament.fields.employees') }}:</span> {{ $number($activeEmployees) }} / {{ $number($employees) }}</div>
                <div><span class="text-gray-500">{{ __('admin.organization_overview.failed_calls_7d') }}:</span> <span class="{{ $failedCalls > 0 ? 'text-danger-600 font-semibold' : '' }}">{{ $number($failedCalls) }}</span></div>
                <div class="col-span-2"><span class="text-gray-500">{{ __('filament.fields.employer') }}:</span> {{ $organization->employer?->name ?? '—' }} <span dir="ltr" class="text-xs text-gray-500">{{ $organization->employer?->phone }}</span></div>
                <div class="col-span-2"><span class="text-gray-500">{{ __('filament.fields.employer_last_login_at') }}:</span> {{ JalaliDate::datetime($organization->employer?->last_login_at, __('filament.fields.never_logged_in')) }}</div>
            </div>
        </x-filament::section>

        <x-filament::section :heading="__('admin.onboarding.heading')">
            <ul class="space-y-2 text-sm">
                @foreach (\App\Services\PlatformAnalytics\EngagementMetrics::ONBOARDING_STEPS as $step)
                    <li class="flex items-center gap-2">
                        @if ($onboarding[$step] ?? false)
                            <x-filament::icon icon="heroicon-s-check-circle" class="h-5 w-5 text-success-500" />
                        @else
                            <x-filament::icon icon="heroicon-o-minus-circle" class="h-5 w-5 text-gray-400" />
                        @endif
                        <span class="{{ ($onboarding[$step] ?? false) ? '' : 'text-gray-500' }}">{{ __('admin.onboarding.steps.'.$step) }}</span>
                    </li>
                @endforeach
            </ul>
        </x-filament::section>

        <x-filament::section :heading="__('admin.organization_overview.integration_errors')">
            <div class="divide-y text-sm dark:divide-gray-700">
                @forelse ($integrationErrors as $error)
                    <div class="py-2">
                        <div class="flex justify-between gap-2 text-xs text-gray-500">
                            <span dir="ltr">{{ $error['source'] }}</span>
                            <span>{{ JalaliDate::datetime($error['at']) }}</span>
                        </div>
                        <p class="mt-1 break-words">{{ \Illuminate\Support\Str::limit($error['message'] ?? '—', 160) }}</p>
                    </div>
                @empty
                    <p class="text-gray-500">{{ __('admin.organization_overview.no_integration_errors') }}</p>
                @endforelse
            </div>
        </x-filament::section>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-filament::section :heading="__('admin.notes.heading')">
            <div class="divide-y text-sm dark:divide-gray-700">
                @forelse ($notes as $note)
                    <div class="py-3" wire:key="note-{{ $note->id }}">
                        <div class="flex items-center justify-between text-xs text-gray-500">
                            <span>{{ $note->author?->name ?? '—' }} · {{ JalaliDate::datetime($note->created_at) }}</span>
                            <button type="button" class="text-danger-600 hover:underline" wire:click="deleteNote({{ $note->id }})" wire:confirm="{{ __('admin.notes.delete_confirm') }}">{{ __('admin.notes.delete') }}</button>
                        </div>
                        <p class="mt-1 whitespace-pre-line">{{ $note->body }}</p>
                    </div>
                @empty
                    <p class="text-gray-500">{{ __('admin.notes.empty') }}</p>
                @endforelse
            </div>
        </x-filament::section>

        <x-filament::section :heading="__('admin.organization_overview.timeline')">
            <ol class="relative space-y-4 border-s border-gray-200 ps-4 text-sm dark:border-gray-700">
                @forelse ($timeline as $event)
                    <li>
                        <span class="absolute -start-1.5 mt-1.5 h-3 w-3 rounded-full bg-primary-500"></span>
                        <p class="text-xs text-gray-500">{{ JalaliDate::datetime($event['at']) }}</p>
                        <p class="font-medium">{{ $event['title'] }}</p>
                        @if ($event['description'])
                            <p class="text-gray-500">{{ \Illuminate\Support\Str::limit($event['description'], 200) }}</p>
                        @endif
                    </li>
                @empty
                    <p class="text-gray-500">{{ __('admin.organization_overview.no_activity') }}</p>
                @endforelse
            </ol>
        </x-filament::section>
    </div>
</x-filament-panels::page>
