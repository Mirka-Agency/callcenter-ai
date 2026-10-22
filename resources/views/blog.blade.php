<x-landing.shell
    :title="'وبلاگ آوایار — '.config('app.name')"
    description="مقالات، راهنماها و تجربیات کاربردی برای رشد کسب‌وکار با هوش مصنوعی و تحلیل مکالمات مرکز تماس."
>
    <x-landing.blog-index
        :posts="$posts"
        :categories="$categories"
        :active-category="$activeCategory"
        :query="$query"
    />
</x-landing.shell>
