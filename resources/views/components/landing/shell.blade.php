@props([
    'title',
    'description',
    'canonical' => null,
    'ogTitle' => null,
    'ogDescription' => null,
    'ogImage' => null,
    'robots' => null,
])

<!DOCTYPE html>
<html lang="fa" dir="rtl" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $description }}">
    @if ($robots)
        <meta name="robots" content="{{ $robots }}">
    @endif
    <title>{{ $title }}</title>
    @if ($canonical)
        <link rel="canonical" href="{{ $canonical }}">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:locale" content="fa_IR">
    <meta property="og:title" content="{{ $ogTitle ?? $title }}">
    <meta property="og:description" content="{{ $ogDescription ?? $description }}">
    @if ($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
    @endif
    @if ($canonical)
        <meta property="og:url" content="{{ $canonical }}">
    @endif
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/saas.css', 'resources/css/landing.css', 'resources/js/landing.js'])
    <script>
        (function () {
            document.documentElement.classList.toggle('dark', localStorage.getItem('theme') === 'dark');
        })();
    </script>
</head>
<body class="bg-zinc-50 font-sans text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <div class="landing-frame">
        <x-landing.navbar />

        <main>
            {{ $slot }}
        </main>

        <x-landing.footer />
    </div>

    <x-landing.consult-dialog />
    <x-landing.demo-dialog />
</body>
</html>
