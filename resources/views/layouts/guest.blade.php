<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name', 'Laravel') }}</title>
        <meta name="description" content="{{ $description ?? 'Offizieller MADDRAX Fanclub e. V. – Informationen zu Projekten, Terminen und Mitgliedschaft.' }}">
        <meta property="og:title" content="{{ $title ?? config('app.name', 'Laravel') }}">
        <meta property="og:description" content="{{ $description ?? 'Offizieller MADDRAX Fanclub e. V. – Informationen zu Projekten, Terminen und Mitgliedschaft.' }}">
        <meta property="og:image" content="{{ $socialImage }}">
        <meta property="og:type" content="website">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $title ?? config('app.name', 'Laravel') }}">
        <meta name="twitter:description" content="{{ $description ?? 'Offizieller MADDRAX Fanclub e. V. – Informationen zu Projekten, Terminen und Mitgliedschaft.' }}">
        <meta name="twitter:image" content="{{ $socialImage }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Styles -->
        @livewireStyles
    </head>
    <body>
        <div class="font-sans text-gray-900 dark:text-gray-100 antialiased">
            {{ $slot }}
        </div>

        @livewireScripts
    </body>
</html>
