@props([
    'post',
])

<article class="blog-card">
    <a class="blog-card__media" href="{{ route('blog.show', $post['slug']) }}">
        <x-landing.blog-cover :theme="$post['category']" :tone="$post['tone']" />
        <span class="blog-card__badge">
            <x-landing.blog-icon :name="$post['category_icon']" />
            {{ $post['category_label'] }}
        </span>
    </a>
    <div class="blog-card__body">
        <p class="blog-card__meta">
            <span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="8" /><path stroke-linecap="round" d="M12 8v4.5l3 2" /></svg>
                {{ \App\Support\PersianNumber::format($post['minutes']) }} دقیقه مطالعه
            </span>
            <span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="4" y="5.5" width="16" height="14" rx="2" /><path stroke-linecap="round" d="M8 3.5v4M16 3.5v4M4 10h16" /></svg>
                {{ $post['published_on'] }}
            </span>
        </p>
        <h2>
            <a href="{{ route('blog.show', $post['slug']) }}">{{ $post['title'] }}</a>
        </h2>
        <p class="blog-card__excerpt">{{ $post['excerpt'] }}</p>
        <a class="blog-card__more" href="{{ route('blog.show', $post['slug']) }}">
            مطالعه بیشتر
            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M12 5 7 10l5 5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" /></svg>
        </a>
    </div>
</article>
