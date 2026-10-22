@props([
    'post',
    'related' => [],
])

<article class="blog-page blog-article" aria-labelledby="article-title">
    <div class="blog-page__inner">
        <nav class="blog-crumb blog-crumb--article" aria-label="مسیر">
            <a href="{{ route('home') }}">خانه</a>
            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M12 5 7 10l5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
            <a href="{{ route('blog') }}">وبلاگ</a>
            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M12 5 7 10l5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
            <span aria-current="page">{{ $post['title'] }}</span>
        </nav>

        <header class="blog-article__header">
            <p class="blog-card__badge blog-article__badge">
                <x-landing.blog-icon :name="$post['category_icon']" />
                {{ $post['category_label'] }}
            </p>
            <h1 id="article-title">{{ $post['title'] }}</h1>
            <p class="blog-card__meta">
                <span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="8" /><path stroke-linecap="round" d="M12 8v4.5l3 2" /></svg>
                    {{ \App\Support\PersianNumber::format($post['minutes']) }} دقیقه مطالعه
                </span>
                <span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="4" y="5.5" width="16" height="14" rx="2" /><path stroke-linecap="round" d="M8 3.5v4M16 3.5v4M4 10h16" /></svg>
                    {{ $post['published_on'] }}
                </span>
                @if (! empty($post['author_display_name']))
                    <span>{{ $post['author_display_name'] }}</span>
                @endif
            </p>
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

        <a class="blog-article__back" href="{{ route('blog') }}">
            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M8 5l5 5-5 5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" /></svg>
            بازگشت به وبلاگ
        </a>

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
