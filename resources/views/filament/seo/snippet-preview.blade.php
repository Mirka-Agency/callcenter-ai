<div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900" dir="rtl">
    <p class="mb-2 text-xs font-medium text-gray-500">{{ __('seo.fields.preview') }}</p>
    <p class="truncate text-xs text-gray-600 dark:text-gray-400" dir="ltr">{{ urldecode($url) }}</p>
    <p class="mt-1 truncate text-lg leading-snug text-[#1a0dab] dark:text-[#8ab4f8]">{{ \Illuminate\Support\Str::limit($title, 70) }}</p>
    <p class="mt-1 line-clamp-2 text-sm text-gray-700 dark:text-gray-300">{{ \Illuminate\Support\Str::limit($description ?: __('seo.fields.no_description'), 165) }}</p>
</div>
