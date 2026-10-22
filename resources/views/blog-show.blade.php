<x-landing.shell
    :title="($post['meta_title'] ?? $post['title']).' — وبلاگ آوایار'"
    :description="$post['meta_description'] ?? $post['excerpt']"
    :canonical="$post['canonical_url'] ?? null"
    :og-title="$post['og_title'] ?? null"
    :og-description="$post['og_description'] ?? null"
    :og-image="$post['og_image_url'] ?? null"
>
    <x-landing.blog-article :post="$post" :related="$related" />
</x-landing.shell>
