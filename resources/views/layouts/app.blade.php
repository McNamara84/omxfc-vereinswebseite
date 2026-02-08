<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name', 'OMXFC e. V.') }}</title>
    <meta name="description" content="{{ $description ?? 'Der Offizielle MADDRAX Fanclub e. V. vernetzt Fans der postapokalyptischen Romanserie und informiert über Projekte, Termine und Mitgliedschaft.' }}">
    <meta property="og:title" content="{{ $title ?? config('app.name', 'OMXFC e. V.') }}">
    <meta property="og:description" content="{{ $description ?? 'Der Offizielle MADDRAX Fanclub e. V. vernetzt Fans der postapokalyptischen Romanserie und informiert über Projekte, Termine und Mitgliedschaft.' }}">
    <meta property="og:image" content="{{ $socialImage }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="de_DE">
    <meta property="og:site_name" content="Offizieller MADDRAX Fanclub e. V.">
    <meta property="og:url" content="{{ request()->url() }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title ?? config('app.name', 'OMXFC e. V.') }}">
    <meta name="twitter:description" content="{{ $description ?? 'Der Offizielle MADDRAX Fanclub e. V. vernetzt Fans der postapokalyptischen Romanserie und informiert über Projekte, Termine und Mitgliedschaft.' }}">
    <meta name="twitter:image" content="{{ $socialImage }}">
    <link rel="canonical" href="{{ request()->url() }}" />
    <!-- Favicon -->
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon/apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon/favicon-16x16.png') }}">
    <link rel="manifest" href="{{ asset('favicon/site.webmanifest') }}">
    <link rel="shortcut icon" href="{{ asset('favicon/favicon.ico') }}">
    <meta name="msapplication-TileColor" content="#ffffff">
    <meta name="theme-color" content="#ffffff">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <!-- Styles -->
    @include('layouts.partials.theme-bootstrap')
    @vite(['resources/css/app.css'])
    {{-- Zusätzliche Head-Inhalte (z.B. JSON-LD für SEO) --}}
    {{ $head ?? '' }}
</head>

<body class="font-sans antialiased">
    <x-banner />
    <div class="min-h-screen bg-base-200 xl:pt-24">
        @livewire('navigation-menu')

        {{-- maryUI Main-Layout --}}
        <x-main full-width>
            <x-slot:content>
                {{ $slot }}
            </x-slot:content>
        </x-main>
    </div>

    <!-- Footer -->
    <x-footer />

    @stack('modals')
    @stack('scripts')

    @vite(['resources/js/app.js'])
</body>
</html>