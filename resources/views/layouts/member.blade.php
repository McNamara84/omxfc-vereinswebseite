<!DOCTYPE html>
<html lang="de" data-theme="caramellatte">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? config('app.name', 'OMXFC e. V.') }}</title>
    <meta name="description" content="{{ $description ?? 'Der interne Mitgliederbereich des Offiziellen MADDRAX Fanclub e. V.' }}">
    <meta property="og:title" content="{{ $title ?? config('app.name', 'OMXFC e. V.') }}">
    <meta property="og:description" content="{{ $description ?? 'Der interne Mitgliederbereich des Offiziellen MADDRAX Fanclub e. V.' }}">
    <meta property="og:image" content="{{ $socialImage }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="de_DE">
    <meta property="og:site_name" content="Offizieller MADDRAX Fanclub e. V.">
    <meta property="og:url" content="{{ request()->url() }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title ?? config('app.name', 'OMXFC e. V.') }}">
    <meta name="twitter:description" content="{{ $description ?? 'Der interne Mitgliederbereich des Offiziellen MADDRAX Fanclub e. V.' }}">
    <meta name="twitter:image" content="{{ $socialImage }}">
    <link rel="canonical" href="{{ request()->url() }}" />
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon/apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon/favicon-16x16.png') }}">
    <link rel="manifest" href="{{ asset('favicon/site.webmanifest') }}">
    <link rel="shortcut icon" href="{{ asset('favicon/favicon.ico') }}">
    <meta name="msapplication-TileColor" content="#ffffff">
    <meta name="theme-color" content="#ffffff">
    @php($isMinimalTestLayout = app()->runningUnitTests() && config('app.testing_minimal_layout', false))
    @php($shouldSkipViteAssets = $isMinimalTestLayout && config('app.testing_skip_vite_assets', true))
    @include('layouts.partials.theme-bootstrap')
    @unless ($shouldSkipViteAssets)
        @vite(['resources/css/app.css'])
    @endunless
    {{ $head ?? '' }}
</head>

<body class="font-sans antialiased">
    <x-banner />

    @if ($isMinimalTestLayout)
        <div class="omxfc-app-shell min-h-screen bg-base-200">
            <main class="relative w-full pb-12 pt-3 sm:pt-5 lg:pb-16">
                {{ $slot }}
            </main>
        </div>
    @else
        <div
            class="omxfc-app-shell min-h-screen bg-base-200"
            x-data="{
                memberDrawerOpen: false,
                drawerElement() { return document.getElementById('member-drawer') },
                syncMemberDrawer() { this.memberDrawerOpen = Boolean(this.drawerElement()?.checked) },
                toggleMemberDrawer() {
                    const drawer = this.drawerElement();
                    if (!drawer) return;
                    drawer.checked = !drawer.checked;
                    drawer.dispatchEvent(new Event('change', { bubbles: true }));
                },
                closeMemberDrawer() {
                    const drawer = this.drawerElement();
                    if (!drawer) return;
                    drawer.checked = false;
                    drawer.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }"
            x-init="$nextTick(() => { const drawer = drawerElement(); if (drawer) drawer.addEventListener('change', () => syncMemberDrawer()); document.addEventListener('livewire:navigated', () => closeMemberDrawer()); syncMemberDrawer() })"
            x-on:member-drawer-close.window="closeMemberDrawer()"
            x-on:keydown.escape.window="if (memberDrawerOpen) { closeMemberDrawer(); $nextTick(() => $refs.memberDrawerToggle?.focus()) }"
        >
            @include('navigation.member-navbar')

            <x-mary-main with-nav full-width collapse-text="Navigation einklappen">
                <x-slot:sidebar drawer="member-drawer" collapsible class="bg-base-100 shadow-sm">
                    <livewire:navigation-menu variant="member-sidebar" :key="'member-sidebar-navigation'" />
                </x-slot:sidebar>

                <x-slot:content class="!p-0">
                    <div class="relative w-full pb-12 pt-3 sm:pt-5 lg:pb-16">
                        {{ $slot }}
                    </div>
                </x-slot:content>
            </x-mary-main>

            @include('layouts.partials.tour-runner')
        </div>
    @endif

    @auth
        <livewire:website-feedback />
    @endauth

    @stack('modals')
    @stack('scripts')

    @unless($isMinimalTestLayout)
        @persist('toast')
            <x-toast />
        @endpersist
        @include('layouts.partials.flash-toast-bridge')
    @endunless

    @unless ($shouldSkipViteAssets)
        @vite(['resources/js/app.js'])
    @endunless
</body>
</html>
