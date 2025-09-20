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
    <!-- Styles (NUR CSS) -->
    @include('layouts.partials.theme-bootstrap')
    @vite(['resources/css/app.css'])
    @livewireStyles
</head>

<body class="font-sans antialiased">
    <!-- Livewire Scripts -->
    <script src="/livewire/livewire.min.js" 
        data-csrf="{{ csrf_token() }}" 
        data-update-uri="/livewire/update" 
        data-navigate-once="true">
    </script>

    <x-banner />
    <div class="min-h-screen text-gray-900 dark:text-gray-100 xl:pt-24">
        @livewire('navigation-menu')
        <!-- Page Heading -->
        @if (isset($header))
            <header class="bg-white dark:bg-gray-800 shadow">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endif
        <!-- Page Content -->
        <main class="text-gray-900 dark:text-gray-100">
            {{ $slot }}
        </main>
    </div>
    <!-- Footer hier einfügen -->
    <x-footer />
    @stack('modals')

    <!-- Alpine/JS -->
    @vite(['resources/js/app.js'])
</body>
</html>