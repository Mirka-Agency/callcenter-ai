<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} — ورود</title>
    @vite(['resources/css/saas.css', 'resources/js/saas.js'])
    @livewireStyles
    <script>
        (function () {
            const dark = localStorage.getItem('theme') === 'dark';
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>
</head>
<body class="saas-shell flex min-h-screen items-center justify-center px-4">
    <div class="w-full max-w-md">
        {{ $slot }}
    </div>
    @livewireScripts
</body>
</html>
