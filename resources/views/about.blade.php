<!DOCTYPE html>
<html lang="fa" dir="rtl" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-seo.head :meta="\App\Support\Seo\SeoMeta::resolve(\App\Support\Seo\SeoMeta::forPage('about', ['canonical' => route('about')]))" />
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
            <x-landing.about-hero />
            <x-landing.partners :intro="false" />
            <x-landing.about-mission />
            <x-landing.about-team />
        </main>

        <x-landing.footer />
    </div>

    <x-landing.consult-dialog />
    <x-landing.demo-dialog />
</body>
</html>
