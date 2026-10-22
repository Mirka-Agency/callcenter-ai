@props([
    'paginator',
])

@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $start = max(1, $current - 2);
        $end = min($last, $start + 4);
        $start = max(1, $end - 4);
    @endphp
    <nav class="blog-pager" aria-label="صفحه‌بندی مقالات">
        @if ($paginator->onFirstPage())
            <span class="blog-pager__arrow is-disabled" aria-hidden="true">
                <svg viewBox="0 0 20 20" fill="none"><path d="M8 5l5 5-5 5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </span>
        @else
            <a class="blog-pager__arrow" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="صفحه قبل">
                <svg viewBox="0 0 20 20" fill="none"><path d="M8 5l5 5-5 5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </a>
        @endif

        @foreach ($paginator->getUrlRange($start, $end) as $page => $url)
            @if ($page === $current)
                <span class="blog-pager__page is-current" aria-current="page">{{ \App\Support\PersianNumber::format($page) }}</span>
            @else
                <a class="blog-pager__page" href="{{ $url }}" aria-label="صفحه {{ \App\Support\PersianNumber::format($page) }}">{{ \App\Support\PersianNumber::format($page) }}</a>
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a class="blog-pager__arrow" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="صفحه بعد">
                <svg viewBox="0 0 20 20" fill="none"><path d="M12 5 7 10l5 5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </a>
        @else
            <span class="blog-pager__arrow is-disabled" aria-hidden="true">
                <svg viewBox="0 0 20 20" fill="none"><path d="M12 5 7 10l5 5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </span>
        @endif
    </nav>
@endif
