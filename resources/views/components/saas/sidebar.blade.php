@php
    $user = auth()->user();
@endphp

<aside
    class="saas-sidebar"
    :class="{ 'saas-sidebar--open': $store.layout.sidebarOpen }"
    data-tour="sidebar"
>
    <div class="saas-sidebar-header">
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-bold text-zinc-900 dark:text-white">{{ config('app.name') }}</p>
            <p class="mt-0.5 text-xs text-zinc-500">{{ $portal === 'employer' ? 'پنل کارفرما' : 'فضای کار کارشناس' }}</p>
            @if ($impersonationContext ?? null)
                <span class="impersonation-sidebar-badge mt-1">ورود به‌جای کاربر</span>
            @endif
        </div>

        <button
            type="button"
            class="saas-sidebar-close"
            @click="$store.layout.closeSidebar()"
            aria-label="بستن منو"
        >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    @if ($user)
        <a
            href="{{ route(($portal ?? 'employer').'.profile.edit') }}"
            class="saas-sidebar-user"
            @click="$store.layout.closeSidebar()"
        >
            <x-saas.avatar :user="$user" size="sm" ring />
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ $user->name }}</p>
                <p class="truncate text-xs text-zinc-500">{{ $user->email }}</p>
            </div>
            <svg class="h-4 w-4 shrink-0 text-zinc-400 rtl:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
            </svg>
        </a>
    @endif

    <nav class="flex-1 space-y-1 overflow-y-auto overscroll-contain px-3 py-3 lg:p-4" aria-label="منوی اصلی">
        @foreach ($navItems as $item)
            @php
                $isActive = request()->routeIs($item['route'].'*') || request()->routeIs($item['route']);
                $highlightedSample = request()->query('sample');
            @endphp
            <a
                href="{{ route($item['route']) }}"
                data-tour-nav="{{ $item['route'] }}"
                @class([
                    'saas-nav-item',
                    'saas-nav-item-active' => $isActive,
                ])
                @click="$store.layout.closeSidebar()"
            >
                <span class="saas-nav-item-icon">
                    <x-saas.icon :name="$item['icon']" class="h-4 w-4" />
                </span>
                <span class="truncate">{{ $item['label'] }}</span>
            </a>

            @if (! empty($item['children']))
                <div class="me-1 space-y-1 border-e-2 border-indigo-100 pe-2 dark:border-indigo-900/40">
                    <p class="px-3 py-1.5 text-[11px] font-semibold tracking-wide text-indigo-500/80 dark:text-indigo-400/80">نمونه مکالمه</p>
                    @foreach ($item['children'] as $child)
                        @php
                            $childSampleId = $child['query']['sample'] ?? null;
                            $childActive = $isActive && $highlightedSample === $childSampleId;
                        @endphp
                        <a
                            href="{{ route($child['route'], $child['query'] ?? []) }}#sample-conversations"
                            @class([
                                'saas-nav-subitem max-lg:min-h-10 max-lg:px-4 max-lg:py-2.5 max-lg:text-sm',
                                'saas-nav-subitem-active' => $childActive,
                                'opacity-50' => ! ($child['available'] ?? true),
                            ])
                            @click="$store.layout.closeSidebar()"
                        >
                            {{ $child['label'] }}
                        </a>
                    @endforeach
                </div>
            @endif
        @endforeach
    </nav>

    <div class="shrink-0 border-t border-zinc-200/80 p-3 dark:border-zinc-800 lg:p-4">
        @if ($impersonationContext ?? null)
            <form method="POST" action="{{ route('impersonation.stop') }}" class="mb-2">
                @csrf
                <button type="submit" class="saas-nav-item w-full text-start text-amber-700 dark:text-amber-300">
                    <span class="saas-nav-item-icon">
                        <x-saas.icon name="home" class="h-4 w-4" />
                    </span>
                    بازگشت به مدیریت
                </button>
            </form>
        @else
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="saas-nav-item w-full text-start text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-950/30 dark:hover:text-red-300">
                    <span class="saas-nav-item-icon">
                        <x-saas.icon name="logout" class="h-4 w-4" />
                    </span>
                    خروج از حساب
                </button>
            </form>
        @endif
    </div>
</aside>

<div
    class="saas-sidebar-overlay"
    :class="{ 'is-visible': $store.layout.sidebarOpen }"
    :aria-hidden="! $store.layout.sidebarOpen"
    @click="$store.layout.closeSidebar()"
></div>
