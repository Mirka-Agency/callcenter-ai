<x-landing.shell
    :title="__('seo.gone.title').' — '.config('app.name')"
    :description="__('seo.gone.description')"
    robots="noindex, follow"
>
    <section class="blog-page">
        <div class="blog-page__inner">
            <div class="blog-empty">
                <h1>{{ __('seo.gone.title') }}</h1>
                <p>{{ __('seo.gone.description') }}</p>
                <a href="{{ route('home') }}">{{ __('seo.gone.home') }}</a>
            </div>
        </div>
    </section>
</x-landing.shell>
