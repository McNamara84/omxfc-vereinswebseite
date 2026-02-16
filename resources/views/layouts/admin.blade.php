<!DOCTYPE html>
<html lang="de" data-theme="caramellatte">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Admin' }} – {{ config('app.name', 'OMXFC e. V.') }}</title>
    <meta name="robots" content="noindex, nofollow">
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
    {{-- Zusätzliche Head-Inhalte --}}
    {{ $head ?? '' }}
</head>

<body class="font-sans antialiased">
    <x-banner />
    <div class="min-h-screen bg-base-200 xl:pt-24">
        @livewire('navigation-menu')

        {{-- maryUI Main-Layout mit optionaler Sidebar --}}
        <x-main full-width>
            {{-- Optionale Admin-Sidebar --}}
            @isset($sidebar)
                <x-slot:sidebar drawer="admin-drawer" collapsible class="bg-base-100 lg:bg-inherit">
                    {{ $sidebar }}
                </x-slot:sidebar>
            @endisset

            {{-- maryUI erfordert expliziten content-Slot --}}
            <x-slot:content>
                <div class="p-4 lg:p-8 max-w-7xl mx-auto">
                    {{ $slot }}
                </div>
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
