@php
    use App\Application\Crm\Deals\DealAging;
    use App\Application\Crm\Deals\DealMoney;
    $aging = app(DealAging::class)->forDeal($deal);
@endphp

<article
    class="rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-950 {{ ($aging['is_stale'] ?? false) ? 'ring-1 ring-amber-300' : '' }}"
    draggable="true"
    wire:key="deal-card-{{ $deal->id }}"
    x-on:dragstart="event.dataTransfer.setData('text/plain', '{{ $deal->id }}'); event.dataTransfer.effectAllowed = 'move'"
>
    <a href="{{ route($portal.'.crm.deals.show', $deal) }}" class="font-medium text-zinc-900 hover:text-indigo-700 dark:text-white" wire:navigate>{{ $deal->title }}</a>
    @if ($aging['is_stale'] ?? false)
        <p class="mt-1 text-xs font-medium text-amber-700">متوقف / بدون فعالیت</p>
    @endif
    <p class="mt-1 text-xs text-zinc-500">{{ $deal->primaryContact?->customer?->displayName() ?: ($deal->company?->name ?: 'بدون شخص') }}</p>
    <p class="mt-2 text-sm">{{ DealMoney::formatRials($deal->amount_rials) }}</p>
    <p class="mt-1 text-xs text-zinc-500">{{ $deal->owner?->full_name ?: 'بدون مسئول' }}</p>
    <p class="mt-1 text-xs text-zinc-400">{{ $deal->last_activity_at ? shamsi($deal->last_activity_at) : 'بدون فعالیت' }}@if($aging['days_in_current_stage'] !== null) · {{ $aging['days_in_current_stage'] }} روز در مرحله@endif</p>
    <label class="mt-3 block text-xs text-zinc-500">
        <span class="sr-only">انتقال {{ $deal->title }} به مرحله دیگر</span>
        <select class="saas-input mt-1" wire:change="moveDeal({{ $deal->id }}, $event.target.value)">
            @foreach ($stages as $stageOption)
                @if ($stageOption->is_active)
                    <option value="{{ $stageOption->id }}" @selected((int) $deal->crm_pipeline_stage_id === (int) $stageOption->id)>{{ $stageOption->name }}</option>
                @endif
            @endforeach
        </select>
    </label>
</article>
