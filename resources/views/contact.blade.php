<!DOCTYPE html>
<html lang="fa" dir="rtl" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="اگر سوالی دارید یا می‌خواهید بیشتر با آوایار آشنا شوید، از فرم تماس یا راه‌های ارتباطی با ما در ارتباط باشید.">
    <title>تماس با ما — {{ config('app.name') }}</title>
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
            <x-landing.contact-section />
        </main>

        <x-landing.footer />
    </div>

    <x-landing.consult-dialog />
    <x-landing.demo-dialog />
</body>
</html>
