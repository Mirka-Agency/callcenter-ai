@props([
    'title',
    'description' => null,
    'eyebrow' => null,
])

<header {{ $attributes->class(['saas-page-header']) }}>
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="text-sm font-medium uppercase tracking-wider text-indigo-600 dark:text-indigo-400">{{ $eyebrow }}</p>
        @endif
        <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-3xl">{{ $title }}</h1>
        @if ($description)
            <p class="mt-2 max-w-2xl text-zinc-500">{{ $description }}</p>
        @endif
    </div>

    @if (isset($actions))
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endif
</header>
