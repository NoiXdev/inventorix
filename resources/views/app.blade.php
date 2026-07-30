<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ config('app.name', 'Inventorix') }}</title>
    <script>
        (function () {
            const a = localStorage.getItem('appearance') || 'system';
            const dark = a === 'dark' || (a === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>
    @viteReactRefresh
    @vite(['resources/css/app-inertia.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="h-full bg-background text-foreground antialiased">
    @inertia
</body>
</html>
