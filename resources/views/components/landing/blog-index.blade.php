@props([
    'posts',
    'categories',
    'activeCategory' => null,
    'query' => '',
])

<section class="blog-page" aria-labelledby="blog-title">
    <div class="blog-page__inner">
        <header class="blog-hero">
            <div class="blog-hero__copy">
                <nav class="blog-crumb" aria-label="مسیر">
                    <a href="{{ route('home') }}">خانه</a>
                    <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M12 5 7 10l5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    <span aria-current="page">وبلاگ</span>
                </nav>
                <h1 id="blog-title">وبلاگ آوایار</h1>
                <p>مقالات، راهنماها و تجربیات کاربردی برای رشد کسب‌وکار شما با قدرت هوش مصنوعی و تحلیل مکالمات.</p>
            </div>
            <figure class="blog-hero__visual">
                <img
                    src="{{ asset('images/blog/hero-notes.png') }}"
                    width="1024"
                    height="341"
                    alt="برگه‌های تحلیل مکالمه با نشان آوایار و حباب گفتگو"
                    decoding="async"
                    fetchpriority="high"
                >
            </figure>
        </header>

        <div class="blog-layout">
            <div class="blog-main">
                <form class="blog-search" method="get" action="{{ route('blog') }}" role="search">
                    @if ($activeCategory)
                        <input type="hidden" name="category" value="{{ $activeCategory }}">
                    @endif
                    <label>
                        <span class="sr-only">جستجوی مقالات</span>
                        <input
                            type="search"
                            name="q"
                            value="{{ $query }}"
                            placeholder="جستجوی مقالات، موضوعات ..."
                            maxlength="80"
                        >
                    </label>
                    <svg class="blog-search__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <circle cx="11" cy="11" r="6.5" />
                        <path stroke-linecap="round" d="M16 16.5 20 20.5" />
                    </svg>
                </form>

                @if ($posts->isEmpty())
                    <div class="blog-empty">
                        <h2>مقاله‌ای پیدا نشد</h2>
                        <p>عبارت یا دسته‌بندی دیگری را امتحان کنید.</p>
                        <a href="{{ route('blog') }}">نمایش همه مطالب</a>
                    </div>
                @else
                    <div class="blog-grid">
                        @foreach ($posts as $post)
                            <x-landing.blog-card :post="$post" />
                        @endforeach
                    </div>
                    <x-landing.blog-pagination :paginator="$posts" />
                @endif
            </div>

            <aside class="blog-aside" aria-label="پالایش مقالات">
                <section class="blog-panel blog-cats" aria-labelledby="blog-categories-title">
                    <h2 id="blog-categories-title">دسته‌بندی‌ها</h2>
                    <ul>
                        @foreach ($categories as $category)
                            @php
                                $href = route('blog', array_filter([
                                    'category' => $category['slug'],
                                    'q' => $query !== '' ? $query : null,
                                ]));
                                $isActive = $category['slug'] === $activeCategory;
                            @endphp
                            <li>
                                <a href="{{ $href }}" @class(['is-active' => $isActive]) @if ($isActive) aria-current="page" @endif>
                                    <span>
                                        <x-landing.blog-icon :name="$category['icon']" />
                                        {{ $category['label'] }}
                                    </span>
                                    <span class="blog-cats__count">{{ \App\Support\PersianNumber::format($category['count']) }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            </aside>
        </div>
    </div>
</section>
