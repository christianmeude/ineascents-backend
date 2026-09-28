<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Inea Scents admin — manage bookings, packages, customers, inquiries, payments, and calendar.">
        <meta name="theme-color" content="#6a4053">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="Inea Scents">
        <meta property="og:title" content="Inea Scents Admin">
        <meta property="og:description" content="Inea Scents admin — bookings, packages, customers, inquiries, payments, calendar.">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Josefin+Sans:wght@400;600;700&display=swap" rel="stylesheet">

        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        
        <!-- Dark Mode Setup (landing-standard: key `inea-theme`, OS only on first load) -->
        <script>
            try {
                var storedTheme = localStorage.getItem('inea-theme');
                if (storedTheme !== 'dark' && storedTheme !== 'light') {
                    var legacyTheme = localStorage.getItem('theme');
                    if (legacyTheme === 'dark' || legacyTheme === 'light') {
                        storedTheme = legacyTheme;
                    } else if (legacyTheme === 'system') {
                        storedTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                    } else if (!('inea-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                        storedTheme = 'dark';
                    } else {
                        storedTheme = 'light';
                    }
                    localStorage.setItem('inea-theme', storedTheme);
                    localStorage.removeItem('theme');
                }
                document.documentElement.classList.toggle('dark', storedTheme === 'dark');
            } catch (e) {
                // Private mode: fall back to OS preference without persisting.
                if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.classList.add('dark');
                }
            }
        </script>
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
