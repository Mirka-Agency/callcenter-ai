@php
    use App\Application\Crm\Deals\DealMoney;
    use App\Support\PersianNumber;
@endphp

<div class="saas-page space-y-6">
    <nav class="text-sm text-zinc-500" aria-label="مسیر">
        <span>CRM</span>
        <span class="mx-2">/</span>
        <span class="text-zinc-800 dark:text-zinc-200">نمای کلی</span>
    </nav>

    <x-saas.page-header title="نمای کلی فروش" description="آمار از معاملات واقعی همین سازمان است. مبلغ‌ها به ریال ذخیره شده‌اند.">
        <x-slot:actions>
            @if ($pipelines->isNotEmpty())
                <label class="text-sm text-zinc-600">
                    <span class="sr-only">پایپ‌لاین</span>
                    <select wire:model.live="pipelineId" class="saas-input">
                        @foreach ($pipelines as $pipeline)
                            <option value="{{ $pipeline->id }}">{{ $pipeline->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
        </x-slot:actions>
    </x-saas.page-header>

    @if ($pipelines->isEmpty())
        <x-saas.empty-state title="هنوز پایپ‌لاینی نیست" description="پس از ساخت پایپ‌لاین و معامله، آمار اینجا از داده واقعی پر می‌شود." />
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <x-saas.stat-card label="معاملات باز" :value="PersianNumber::format($summary['open_count'])" />
            <x-saas.stat-card label="ارزش معاملات باز" :value="DealMoney::formatRials($summary['open_amount_rials'])" :hint="$summary['open_without_amount'] > 0 ? 'مبلغ‌های خالی در جمع نیامده‌اند.' : null" />
            <x-saas.stat-card label="معاملات موفق" :value="PersianNumber::format($summary['won_count'])" tone="good" />
            <x-saas.stat-card label="معاملات ناموفق" :value="PersianNumber::format($summary['lost_count'])" tone="bad" />
            <x-saas.stat-card label="نرخ موفقیت بسته‌شده‌ها" :value="$summary['win_rate'] === null ? '—' : PersianNumber::format($summary['win_rate'], 1).'٪'" />
            <x-saas.stat-card label="بدون فعالیت اخیر" :value="PersianNumber::format($summary['stale_count'])" :hint="$summary['stale_inactivity_hint'] ?? 'باز و بدون فعالیت در ۱۴ روز اخیر، یا عبور از آستانه توقف مرحله'" />
        </div>

        <section class="saas-card p-4">
            <h2 class="text-lg font-semibold">ارزش باز به تفکیک مرحله</h2>
            <p class="mt-1 text-sm text-zinc-500">{{ $summary['pipeline']?->name }}</p>
            <ul class="mt-4 divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($summary['stages'] as $stage)
                    <li class="flex items-center justify-between gap-4 py-3 text-sm" wire:key="stage-stat-{{ $stage['id'] }}">
                        <span class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $stage['color'] }}"></span>
                            {{ $stage['name'] }}
                        </span>
                        <span>{{ PersianNumber::format($stage['count']) }} معامله · {{ DealMoney::formatRials($stage['amount_rials']) }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
