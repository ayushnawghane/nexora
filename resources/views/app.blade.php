<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ auth()->user()?->theme === 'light' || auth()->user()?->theme === 'dark' ? auth()->user()->theme : '' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Nexora') }}</title>

        {{-- Resolve the theme before first paint so there's no flash of the wrong one. --}}
        <script>
            (function () {
                var root = document.documentElement;
                if (root.dataset.theme) return;
                var saved = null;
                try { saved = localStorage.getItem('nexora-theme'); } catch (e) {}
                if (saved !== 'light' && saved !== 'dark') {
                    saved = window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
                }
                root.dataset.theme = saved;
            })();
        </script>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
