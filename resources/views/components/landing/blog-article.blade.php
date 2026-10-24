@props([
    'post',
    'related' => [],
])

@php
    $shareUrl = $post['canonical_url'] ?? route('blog.show', $post['slug']);
    $shareTitle = $post['title'];
    $tags = $post['tags'] ?? [];
    $authorName = $post['author_display_name'] ?? '';
    $authorInitials = $post['author_initials'] ?? '';
@endphp

<article class="blog-page blog-article" aria-labelledby="article-title">
    <div class="blog-page__inner blog-article__inner">
        <header class="blog-article__intro">
            <nav class="blog-crumb blog-crumb--article" aria-label="مسیر">
                <a href="{{ route('home') }}">خانه</a>
                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M12 5 7 10l5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                <a href="{{ route('blog') }}">وبلاگ</a>
                @if (! empty($post['category_label']))
                    <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M12 5 7 10l5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    <a href="{{ route('blog.category', $post['category']) }}">{{ $post['category_label'] }}</a>
                @endif
                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M12 5 7 10l5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                <span aria-current="page">{{ $post['title'] }}</span>
            </nav>

            <h1 id="article-title">{{ $post['title'] }}</h1>

            @if (! empty($post['excerpt']))
                <p class="blog-article__excerpt">{{ $post['excerpt'] }}</p>
            @endif

            <div class="blog-article__meta">
                @if ($authorName !== '')
                    <div class="blog-article__author">
                        <span class="blog-article__avatar" aria-hidden="true">{{ $authorInitials }}</span>
                        <div class="blog-article__author-text">
                            <span class="blog-article__author-name">{{ $authorName }}</span>
                            <span class="blog-article__author-role">نویسنده</span>
                        </div>
                    </div>
                    <span class="blog-article__meta-sep" aria-hidden="true"></span>
                @endif

                @if (! empty($post['published_on']))
                    <span class="blog-article__meta-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="4" y="5.5" width="16" height="14" rx="2" /><path stroke-linecap="round" d="M8 3.5v4M16 3.5v4M4 10h16" /></svg>
                        {{ $post['published_on'] }}
                    </span>
                    <span class="blog-article__meta-sep" aria-hidden="true"></span>
                @endif

                <span class="blog-article__meta-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="8" /><path stroke-linecap="round" d="M12 8v4.5l3 2" /></svg>
                    {{ \App\Support\PersianNumber::format($post['minutes']) }} دقیقه مطالعه
                </span>
            </div>
        </header>

        @if (! empty($post['cover_image_url']))
            <figure class="blog-article__cover blog-article__cover--photo">
                <img src="{{ $post['cover_image_url'] }}" alt="" loading="lazy">
            </figure>
        @elseif (! empty($post['cover_video_url']))
            <div class="blog-article__cover blog-article__cover--video">
                <video controls preload="metadata" src="{{ $post['cover_video_url'] }}"></video>
            </div>
        @else
            <x-landing.blog-cover class="blog-article__cover" :theme="$post['category']" :tone="$post['tone']" />
        @endif

        <div class="blog-article__body prose-blog">
            {!! $post['body_html'] ?? '' !!}
        </div>

        @if (! empty($post['faq']) && is_array($post['faq']))
            <section class="blog-faq" aria-labelledby="faq-title">
                <h2 id="faq-title">پرسش‌های متداول</h2>
                <div class="blog-faq__list">
                    @foreach ($post['faq'] as $item)
                        <details class="blog-faq__item">
                            <summary>{{ $item['question'] ?? '' }}</summary>
                            <p>{{ $item['answer'] ?? '' }}</p>
                        </details>
                    @endforeach
                </div>
            </section>
        @endif

        <footer class="blog-article__footer">
            @if ($tags !== [])
                <div class="blog-article__tags">
                    <span class="blog-article__footer-label">برچسب‌ها</span>
                    <ul>
                        @foreach ($tags as $tag)
                            <li><span>{{ $tag }}</span></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="blog-article__share">
                <span class="blog-article__footer-label">اشتراک‌گذاری</span>
                <ul>
                    <li>
                        <a
                            href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode($shareUrl) }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="اشتراک در لینکدین"
                        >
                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6.5 8.5A2 2 0 1 1 6.5 4.5a2 2 0 0 1 0 4Zm.1 2.1H4.4V20h2.2V10.6Zm4.2 0H8.7V20h2.1v-4.7c0-1.24.9-2.25 2.05-2.25 1.1 0 1.65.74 1.65 2.2V20H16.7v-5.2c0-2.86-1.53-4.2-3.57-4.2-1.65 0-2.39.92-2.8 1.56h-.04v-1.36Z"/></svg>
                        </a>
                    </li>
                    <li>
                        <a
                            href="https://t.me/share/url?url={{ urlencode($shareUrl) }}&text={{ urlencode($shareTitle) }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="اشتراک در تلگرام"
                        >
                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19.7 5.1 3.9 11.2c-1.08.43-1.07 1.03-.2 1.3l4.05 1.26 1.56 4.8c.19.53.1.74.68.74.45 0 .65-.2.9-.45l2.17-2.11 4.5 3.32c.83.46 1.42.22 1.62-.77L21.05 6.4c.23-1.03-.39-1.5-1.35-1.3Z"/></svg>
                        </a>
                    </li>
                    <li>
                        <a
                            href="https://twitter.com/intent/tweet?url={{ urlencode($shareUrl) }}&text={{ urlencode($shareTitle) }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="اشتراک در X"
                        >
                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.6 4h2.5l-5.5 6.3L21.5 20h-4.7l-3.7-4.8L8.5 20H6l5.9-6.7L2.7 4h4.8l3.3 4.4L17.6 4Zm-.9 14.4h1.4L7.4 5.5H6L16.7 18.4Z"/></svg>
                        </a>
                    </li>
                    <li>
                        <button
                            type="button"
                            class="blog-article__copy"
                            data-copy-url="{{ $shareUrl }}"
                            aria-label="کپی لینک مقاله"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="8" y="8" width="11" height="11" rx="2" /><path stroke-linecap="round" d="M6 16V6a2 2 0 0 1 2-2h10" /></svg>
                        </button>
                    </li>
                </ul>
            </div>
        </footer>

        @if ($related !== [])
            <section class="blog-related" aria-labelledby="related-title">
                <h2 id="related-title">مقاله‌های مرتبط</h2>
                <div class="blog-grid">
                    @foreach ($related as $item)
                        <x-landing.blog-card :post="$item" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</article>

<script>
    (function () {
        document.querySelectorAll('.blog-article__copy[data-copy-url]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var url = btn.getAttribute('data-copy-url') || '';
                if (!url || !navigator.clipboard) return;
                navigator.clipboard.writeText(url).then(function () {
                    btn.classList.add('is-copied');
                    window.setTimeout(function () { btn.classList.remove('is-copied'); }, 1600);
                }).catch(function () {});
            });
        });
    })();
</script>
