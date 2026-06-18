@php
    $activeItem = collect($navItems ?? [])
        ->first(fn (array $item) => request()->routeIs($item['route'].'*') || request()->routeIs($item['route']));
    $activeNavLabel = $activeItem['label'] ?? null;
@endphp

<header class="saas-topbar">
    <div class="flex min-w-0 flex-1 items-center gap-3">
        <button
            type="button"
            class="saas-mobile-menu-btn lg:hidden"
            @click="$store.layout.toggleSidebar()"
            aria-label="منوی ناوبری"
            :aria-expanded="$store.layout.sidebarOpen"
        >
            <span class="saas-mobile-menu-icon" :class="{ 'is-open': $store.layout.sidebarOpen }" aria-hidden="true">
                <span></span>
                <span></span>
                <span></span>
            </span>
        </button>

        <div class="min-w-0 lg:hidden">
            <p class="truncate text-sm font-semibold text-zinc-900 dark:text-white">
                {{ $activeNavLabel ?? ($portal === 'employer' ? 'پنل کارفرما' : 'فضای کار') }}
            </p>
            <p class="truncate text-xs text-zinc-500">{{ config('app.name') }}</p>
        </div>
    </div>

    <div class="flex shrink-0 items-center gap-2 sm:gap-3">
        @if ($impersonationContext ?? null)
            <span class="impersonation-topbar-badge hidden sm:inline">ورود به‌جای کاربر</span>
        @endif
        <button type="button" class="saas-btn-secondary !px-2.5" data-tour="topbar-theme" @click="$store.theme.toggle()" aria-label="تغییر تم">
            <svg x-show="!$store.theme.dark" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
            </svg>
            <svg x-show="$store.theme.dark" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
            </svg>
        </button>
        <a
            href="{{ route(($portal ?? 'employer').'.profile.edit') }}"
            class="flex items-center gap-3 rounded-lg px-2 py-1.5 transition hover:bg-zinc-100 dark:hover:bg-zinc-800"
            title="ویرایش پروفایل"
        >
            <x-saas.avatar :user="auth()->user()" size="sm" ring />
            <div class="hidden text-start sm:block">
                <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ auth()->user()->name }}</p>
                <p class="text-xs text-zinc-500">{{ auth()->user()->email }}</p>
            </div>
        </a>
    </div>
</header>
