@php
    use App\Application\Crm\Deals\DealAging;
    use App\Support\PersianNumber;
    $aging = app(DealAging::class)->forDeal($deal);
    $contact = $deal->primaryContact?->customer;
    $contactLabel = $contact?->displayName()
        ?: ($contact?->phone_number ?: ($deal->company?->name ?: 'بدون مخاطب'));
    $openTasks = (int) ($deal->open_tasks_count ?? 0);
@endphp

<article
    class="crm-deal-card rounded-xl border border-zinc-200 bg-white p-3 shadow-sm dark:border-zinc-800 dark:bg-zinc-950 {{ ($aging['is_stale'] ?? false) ? 'ring-1 ring-amber-300' : '' }}"
    draggable="true"
    wire:key="deal-card-{{ $deal->id }}"
    x-data="{ suppressNavigate: false }"
    x-on:pointerdown="
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const link = event.target.closest('a');
        if (! link || ! $el.contains(link) || ! link.hasAttribute('href')) return;
        link.dataset.dealHref = link.getAttribute('href');
        link.removeAttribute('href');
    "
    x-on:dragstart="
        suppressNavigate = true;
        event.dataTransfer.clearData();
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', '{{ $deal->id }}');
        event.dataTransfer.setDragImage($el, Math.min(48, $el.offsetWidth / 2), 24);
    "
    x-on:dragend="
        const link = $el.querySelector('a[data-deal-href]');
        if (link && link.dataset.dealHref) link.setAttribute('href', link.dataset.dealHref);
        setTimeout(() => { suppressNavigate = false }, 120);
    "
    x-on:pointerup.window="
        const link = $el.querySelector('a[data-deal-href]');
        if (link && link.dataset.dealHref) link.setAttribute('href', link.dataset.dealHref);
    "
    x-on:click.capture="
        if (! suppressNavigate) return;
        event.preventDefault();
        event.stopPropagation();
    "
>
    <a
        href="{{ route($portal.'.crm.deals.show', $deal) }}"
        class="crm-deal-card__title block text-sm font-semibold leading-6 text-zinc-900 hover:text-indigo-700 dark:text-white"
        draggable="true"
        wire:navigate
    >{{ $deal->title }}</a>

    @if ($aging['is_stale'] ?? false)
        <p class="mt-1 text-xs font-medium text-amber-700">متوقف / بدون فعالیت</p>
    @endif

    <div class="mt-2.5 flex items-center gap-1.5 text-xs text-zinc-500">
        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2" />
            <circle cx="10.5" cy="7" r="3.5" />
        </svg>
        <span class="truncate" dir="ltr">{{ $contactLabel }}</span>
    </div>

    <div class="mt-3 flex items-center justify-between gap-2">
        <p class="text-[11px] text-zinc-400">
            {{ $deal->last_activity_at ? shamsi($deal->last_activity_at) : 'بدون فعالیت' }}
        </p>

        @if ($openTasks > 0)
            <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-1.5 py-0.5 text-[11px] font-medium text-amber-800 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-200 dark:ring-amber-900">
                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.5 17a2.5 2.5 0 0 0 5 0" />
                </svg>
                {{ PersianNumber::format($openTasks) }}
            </span>
        @endif
    </div>
</article>
