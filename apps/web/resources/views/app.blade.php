<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        {{-- IMPORTANT: do NOT add a CSRF-token meta tag here — Inertia handles XSRF via cookie (Pitfall 3 from RESEARCH). --}}

        <title inertia>{{ config('app.name', 'Trenchwars') }}</title>

        @routes
        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
