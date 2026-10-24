<x-landing.shell :seo="$seo">
    <x-landing.blog-index
        :posts="$posts"
        :categories="$categories"
        :active-category="$activeCategory"
        :category="$category"
        :query="$query"
    />
</x-landing.shell>
