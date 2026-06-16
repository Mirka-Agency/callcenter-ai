@php
    use App\Support\AnalysisInsightPresenter;
    use App\Support\CustomerPresenter;
    use App\Support\JalaliDate;

    $meta = collect([
        $customer->total_calls.' تماس',
        JalaliDate::ago($customer->last_contact_at),
        $customer->latest_lead_level
            ? 'لید '.AnalysisInsightPresenter::leadLevelLabel($customer->latest_lead_level)
            : null,
        $customer->purchase_intent
            ? 'تمایل '.$customer->purchase_intent
            : null,
    ])->filter()->implode(' · ');
@endphp

<div class="space-y-3">
    <div class="flex items-center gap-4">
        <x-saas.avatar :name="$customer->displayName()" size="md" ring class="shrink-0" />

        <div class="min-w-0 flex-1">
            <h3 class="truncate text-base font-semibold text-zinc-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400">
                {{ $customer->displayName() }}
            </h3>
            <p class="mt-0.5 truncate text-sm text-zinc-500">
                {{ CustomerPresenter::listSubtitle($customer) }}
            </p>
        </div>

        @if ($customer->latest_lead_score)
            <x-saas.score-ring
                :score="$customer->latest_lead_score"
                size="sm"
                label="لید"
                class="shrink-0"
            />
        @endif
    </div>

    @if ($meta !== '')
        <p class="border-t border-zinc-100 pt-3 text-sm text-zinc-600 dark:border-zinc-800 dark:text-zinc-400">
            {{ $meta }}
        </p>
    @endif

    @if ($customer->recommended_next_action)
        <p class="truncate text-xs text-zinc-500">
            <span class="font-medium text-zinc-400">اقدام بعدی:</span>
            {{ $customer->recommended_next_action }}
        </p>
    @endif
</div>
