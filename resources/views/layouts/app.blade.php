<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'OMXFC e. V.') }}</title>
    <!-- Favicon -->
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon/apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon/favicon-16x16.png') }}">
    <link rel="manifest" href="{{ asset('favicon/site.webmanifest') }}">
    <link rel="shortcut icon" href="{{ asset('favicon/favicon.ico') }}">
    <meta name="msapplication-TileColor" content="#FED17E">
    <meta name="theme-color" content="#FED17E">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <!-- Styles (NUR CSS) -->
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
    <div class="min-h-screen bg-apocalypse dark:bg-gray-900 text-black dark:text-gray-100 xl:pt-16">
        @livewire('navigation-menu')
        <!-- Page Heading -->
        @if (isset($header))
            <header class="bg-apocalypse dark:bg-gray-800 shadow">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endif
        <!-- Page Content -->
        <main class="text-black dark:text-gray-100">
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