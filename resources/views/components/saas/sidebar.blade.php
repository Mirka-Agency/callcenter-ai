<aside
    class="saas-sidebar -translate-x-full rtl:translate-x-full lg:translate-x-0 rtl:lg:translate-x-0"
    :class="{ 'translate-x-0 rtl:translate-x-0': sidebarOpen }"
    x-cloak
>
    <div class="flex h-16 items-center gap-3 border-b border-zinc-200/80 px-5 dark:border-zinc-800">
        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-zinc-900 text-sm font-bold text-white dark:bg-white dark:text-zinc-900">
            {{ strtoupper(substr(config('app.name', 'CC'), 0, 1)) }}
        </div>
        <div>
            <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ config('app.name', 'CallCenter') }}</p>
            <p class="text-xs text-zinc-500">{{ $portal === 'employer' ? 'کارفرما' : 'فضای کار' }}</p>
            @if ($impersonationContext ?? null)
                <span class="impersonation-sidebar-badge">ورود به‌جای کاربر</span>
            @endif
        </div>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto p-4">
        @foreach ($navItems as $item)
            @php
                $isActive = request()->routeIs($item['route'].'*') || request()->routeIs($item['route']);
                $highlightedSample = request()->query('sample');
            @endphp
            <a
                href="{{ route($item['route']) }}"
                @class([
                    'saas-nav-item',
                    'saas-nav-item-active' => $isActive,
                ])
            >
                <x-saas.icon :name="$item['icon']" class="h-4 w-4" />
                {{ $item['label'] }}
            </a>

            @if (! empty($item['children']))
                <div class="me-2 space-y-0.5 border-e border-zinc-200 pe-2 dark:border-zinc-800">
                    <p class="px-3 py-1.5 text-[11px] font-medium tracking-wide text-zinc-400">مکالمات نمونه</p>
                    @foreach ($item['children'] as $child)
                        @php
                            $childSampleId = $child['query']['sample'] ?? null;
                            $childActive = $isActive && $highlightedSample === $childSampleId;
                        @endphp
                        <a
                            href="{{ route($child['route'], $child['query'] ?? []) }}#sample-conversations"
                            @class([
                                'saas-nav-subitem',
                                'saas-nav-subitem-active' => $childActive,
                                'opacity-50' => ! ($child['available'] ?? true),
                            ])
                        >
                            {{ $child['label'] }}
                        </a>
                    @endforeach
                </div>
            @endif
        @endforeach
    </nav>

    <div class="border-t border-zinc-200/80 p-4 dark:border-zinc-800">
        @if ($impersonationContext ?? null)
            <form method="POST" action="{{ route('impersonation.stop') }}" class="mb-2">
                @csrf
                <button type="submit" class="saas-nav-item w-full text-start text-amber-700 dark:text-amber-300">
                    <x-saas.icon name="home" class="h-4 w-4" />
                    بازگشت به مدیریت
                </button>
            </form>
        @else
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="saas-nav-item w-full text-start">
                    <x-saas.icon name="home" class="h-4 w-4" />
                    خروج
                </button>
            </form>
        @endif
    </div>
</aside>

<div
    x-show="sidebarOpen"
    x-transition.opacity
    class="fixed inset-0 z-30 bg-zinc-950/50 lg:hidden"
    @click="sidebarOpen = false"
></div>
