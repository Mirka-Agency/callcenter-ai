@php
    $section = function (string $id): string {
        $hash = '#'.$id;

        return request()->routeIs('home') ? $hash : route('home').$hash;
    };
    $links = [
        ['href' => $section('sample-call'), 'label' => 'امکانات', 'current' => false],
        ['href' => $section('journey'), 'label' => 'نحوه کار', 'current' => false],
        ['href' => $section('faq'), 'label' => 'سوالات متداول', 'current' => false],
        ['href' => route('about'), 'label' => 'درباره ما', 'current' => request()->routeIs('about')],
        ['href' => route('contact'), 'label' => 'تماس با ما', 'current' => request()->routeIs('contact')],
    ];
    $authActionHref = auth()->check() ? auth()->user()->portalRoute() : route('login');
    $authActionLabel = auth()->check() ? 'پنل کاربری' : 'ورود';
@endphp

<header class="sticky top-0 z-40 border-b border-line bg-white dark:border-zinc-800 dark:bg-zinc-950">
    <div class="mx-auto grid h-[4.5rem] max-w-6xl grid-cols-[auto_1fr_auto] items-center gap-3 px-4 sm:px-6 lg:grid-cols-[1fr_auto_1fr] lg:gap-6">
        <a href="{{ route('home') }}" class="flex items-center gap-2 justify-self-start">
            <img src="{{ asset('images/journey/avayar-logo.webp') }}" width="480" height="151" alt="آوایار" class="h-8 w-auto dark:brightness-0 dark:invert" decoding="async">
            <x-landing.brand-mark class="h-6 w-7 shrink-0" aria-hidden="true" />
        </a>

        <nav class="hidden items-center justify-center gap-6 text-sm font-medium text-ink lg:flex dark:text-zinc-100" aria-label="بخش‌های صفحه">
            @foreach ($links as $link)
                <a href="{{ $link['href'] }}" @class(['transition hover:text-brand', 'text-brand' => $link['current']]) @if ($link['current']) aria-current="page" @endif>{{ $link['label'] }}</a>
            @endforeach
        </nav>

        <div class="flex items-center justify-self-end gap-2">
            <a href="{{ $authActionHref }}" class="hidden h-10 items-center justify-center rounded-lg border border-line bg-white px-4 text-sm font-medium text-ink transition hover:bg-canvas focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/30 sm:inline-flex dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800">{{ $authActionLabel }}</a>
            <button type="button" class="inline-flex h-10 cursor-pointer items-center justify-center rounded-lg border-0 bg-brand px-3.5 text-sm font-medium text-white transition hover:bg-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/40 sm:px-4" data-consult-open>درخواست مشاوره</button>

            <details class="relative lg:hidden">
                <summary class="inline-flex h-10 w-10 cursor-pointer list-none items-center justify-center rounded-lg border border-line bg-white text-ink marker:content-none [&::-webkit-details-marker]:hidden dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100" aria-label="باز کردن منو">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" />
                    </svg>
                </summary>
                <div class="absolute end-0 z-50 mt-2 w-56 rounded-xl border border-line bg-white p-2 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    @foreach ($links as $link)
                        <a href="{{ $link['href'] }}" @class(['block rounded-lg px-3 py-2 text-sm font-medium hover:bg-canvas dark:hover:bg-zinc-800', 'text-brand' => $link['current'], 'text-ink dark:text-zinc-100' => ! $link['current']]) @if ($link['current']) aria-current="page" @endif onclick="this.closest('details').open=false">{{ $link['label'] }}</a>
                    @endforeach
                    <button type="button" class="block w-full rounded-lg border-0 bg-transparent px-3 py-2 text-start text-sm font-medium text-ink hover:bg-canvas dark:text-zinc-100 dark:hover:bg-zinc-800" data-consult-open>درخواست مشاوره</button>
                    <a href="{{ $authActionHref }}" class="mt-1 block rounded-lg px-3 py-2 text-sm font-medium text-ink hover:bg-canvas sm:hidden dark:text-zinc-100 dark:hover:bg-zinc-800">{{ $authActionLabel }}</a>
                </div>
            </details>
        </div>
    </div>
</header>
