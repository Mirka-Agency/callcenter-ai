<!DOCTYPE html>
<html lang="fa" dir="rtl" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-seo.head :meta="\App\Support\Seo\SeoMeta::resolve(\App\Support\Seo\SeoMeta::forPage('home', ['canonical' => route('home'), 'json_ld' => [\App\Support\Seo\SeoMeta::organizationSchema(), \App\Support\Seo\SeoMeta::websiteSchema()]]))" />
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
            <x-landing.hero />
            <x-landing.partners />
            <x-landing.journey />

            <div class="insight" data-insight>
                <div class="insight__main">
                    <x-landing.challenges />
                    <x-landing.sample-call />
                </div>
                <x-landing.coaching />
                <x-landing.faq />
            </div>
        </main>

        <x-landing.footer />
    </div>

    <x-landing.consult-dialog />
    <x-landing.demo-dialog />
</body>
</html>
