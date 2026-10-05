<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title inertia>{{ config('app.name', 'iden') }} — Admin</title>
    <link rel="icon" href="/images/iden-logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <script>
        window.__ADMIN_BOOT__ = {
            pusherKey: @json(config('broadcasting.connections.pusher.key')),
            pusherCluster: @json(config('broadcasting.connections.pusher.options.cluster')),
        };
    </script>
    @vite(['resources/js/admin/app.tsx'])
    @inertiaHead
</head>
<body class="antialiased">
    @inertia
</body>
</html>
