<!DOCTYPE html>
<html lang="fa" dir="rtl" x-data x-init="$nextTick(() => { $store.layout?.closeSidebar?.(); })" x-bind:class="$store.theme.dark ? 'dark' : ''">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title>{{ $title ?? config('app.name') }}</title>
    @if (($portal ?? '') === 'employer')
        @php
            $onboardingRoutes = \App\Support\Onboarding\EmployerOnboarding::routeUrls();
        @endphp
        <script>
            window.__employerOnboarding = {
                currentRoute: @json(\Illuminate\Support\Facades\Route::currentRouteName()),
                routes: @json($onboardingRoutes),
            };
        </script>
    @endif
    @if (($portal ?? '') === 'employee')
        @php
            $employeeOnboardingRoutes = \App\Support\Onboarding\EmployeeOnboarding::routeUrls();
        @endphp
        <script>
            window.__employeeOnboarding = {
                currentRoute: @json(\Illuminate\Support\Facades\Route::currentRouteName()),
                routes: @json($employeeOnboardingRoutes),
            };
        </script>
    @endif
    @vite(['resources/css/saas.css', 'resources/js/saas.js'])
    @livewireStyles
    <script>
        (function () {
            const dark = localStorage.getItem('theme') === 'dark';
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>
</head>
<body
    class="saas-shell"
    @keydown.escape.window="$store.layout.closeSidebar()"
>
    <script>
        document.documentElement.classList.remove('overflow-hidden');
        document.body.classList.remove('overflow-hidden', 'employer-onboarding-open', 'employee-onboarding-open');
    </script>
    <div id="saas-request-progress" class="saas-request-progress" aria-hidden="true">
        <div class="saas-request-progress__bar"></div>
    </div>

    @include('components.saas.impersonation-banner')

    @include('components.saas.sidebar', ['portal' => $portal ?? 'employer', 'navItems' => $navItems ?? []])

    <div class="saas-main">
    @include('components.saas.topbar', ['portal' => $portal ?? 'employer', 'navItems' => $navItems ?? []])

        <main class="saas-content">
            {{ $slot }}
        </main>
    </div>

    @if (($portal ?? '') === 'employee' && auth()->check())
        <livewire:employee.incoming-call-center />
    @endif

    @auth
        @include('components.saas.toast-stack')
    @endauth

    @if (in_array($portal ?? '', ['employer', 'employee'], true))
        <x-saas.onboarding-tour :portal="$portal" />
    @endif

    @livewireScripts
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('theme', {
                dark: document.documentElement.classList.contains('dark'),
                toggle() {
                    this.dark = !this.dark;
                    localStorage.setItem('theme', this.dark ? 'dark' : 'light');
                    document.documentElement.classList.toggle('dark', this.dark);
                },
            });
        });
    </script>
</body>
</html>
