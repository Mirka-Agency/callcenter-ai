@php
    $scrollIntoViewJsSnippet = '($el.closest("body") || document.querySelector("body")).scrollIntoView()';
@endphp

@if ($paginator->total() > 0)
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between" data-tour="customers-contacts-pagination">
        <label class="inline-flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300">
            <span>1 تا</span>
            <input
                type="number"
                min="1"
                max="100"
                wire:model.blur="perPage"
                class="saas-input w-20 px-2 py-1.5 text-center"
                aria-label="تعداد نمایش در این صفحه"
            >
        </label>

        @if ($paginator->hasPages())
            <nav role="navigation" aria-label="Pagination Navigation" class="flex items-center justify-end">
                <div class="flex justify-between gap-2 sm:hidden">
                    @if ($paginator->onFirstPage())
                        <span class="saas-btn-secondary cursor-not-allowed px-3 py-1.5 text-sm opacity-50">{{ __('pagination.previous') }}</span>
                    @else
                        <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" class="saas-btn-secondary px-3 py-1.5 text-sm">{{ __('pagination.previous') }}</button>
                    @endif

                    @if ($paginator->hasMorePages())
                        <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" class="saas-btn-secondary px-3 py-1.5 text-sm">{{ __('pagination.next') }}</button>
                    @else
                        <span class="saas-btn-secondary cursor-not-allowed px-3 py-1.5 text-sm opacity-50">{{ __('pagination.next') }}</span>
                    @endif
                </div>

                <span class="relative z-0 hidden overflow-hidden rounded-md border border-zinc-200 dark:border-zinc-700 sm:inline-flex rtl:flex-row-reverse">
                    @if ($paginator->onFirstPage())
                        <span class="inline-flex items-center px-2 py-2 text-zinc-400 dark:text-zinc-500" aria-hidden="true">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    @else
                        <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" class="inline-flex items-center px-2 py-2 text-zinc-500 transition hover:bg-zinc-50 hover:text-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-800" aria-label="{{ __('pagination.previous') }}">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    @endif

                    @foreach ($elements as $element)
                        @if (is_string($element))
                            <span class="inline-flex items-center border-s border-zinc-200 px-3 py-2 text-sm text-zinc-400 dark:border-zinc-700">{{ $element }}</span>
                        @endif

                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                <span wire:key="paginator-{{ $paginator->getPageName() }}-page{{ $page }}">
                                    @if ($page == $paginator->currentPage())
                                        <span aria-current="page" class="inline-flex items-center border-s border-zinc-200 bg-zinc-100 px-3 py-2 text-sm font-medium text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">{{ $page }}</span>
                                    @else
                                        <button type="button" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" class="inline-flex items-center border-s border-zinc-200 px-3 py-2 text-sm text-zinc-600 transition hover:bg-zinc-50 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                            {{ $page }}
                                        </button>
                                    @endif
                                </span>
                            @endforeach
                        @endif
                    @endforeach

                    @if ($paginator->hasMorePages())
                        <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" class="inline-flex items-center border-s border-zinc-200 px-2 py-2 text-zinc-500 transition hover:bg-zinc-50 hover:text-zinc-800 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800" aria-label="{{ __('pagination.next') }}">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    @else
                        <span class="inline-flex items-center border-s border-zinc-200 px-2 py-2 text-zinc-400 dark:border-zinc-700 dark:text-zinc-500" aria-hidden="true">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    @endif
                </span>
            </nav>
        @endif
    </div>
@endif
