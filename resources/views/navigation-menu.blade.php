<nav x-data="{ open: false }" class="bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('home') }}">
                        <x-application-mark class="block h-9 w-auto" />
                    </a>
                </div>

                <!-- Navigation Links -->
<div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
    @auth
        <x-nav-link href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')">
            {{ __('Dashboard') }}
        </x-nav-link>
        <x-nav-link href="{{ route('mitglieder.index') }}" :active="request()->routeIs('mitglieder.index')">
            {{ __('Mitgliederliste') }}
        </x-nav-link>
        <x-nav-link href="{{ route('mitglieder.karte') }}" :active="request()->routeIs('mitglieder.karte')">
            {{ __('Mitgliederkarte') }}
        </x-nav-link>
        <x-nav-link href="{{ route('protokolle') }}" :active="request()->routeIs('protokolle')">
            {{ __('Protokolle') }}
        </x-nav-link>
        <x-nav-link href="{{ route('fotogalerie') }}" :active="request()->routeIs('fotogalerie')">
            {{ __('Fotos') }}
        </x-nav-link>
    @endauth

    @guest
        <!--<x-nav-link href="{{ route('home') }}" :active="request()->routeIs('home')">
            {{ __('Startseite') }}
        </x-nav-link>-->
        <x-nav-link href="{{ route('chronik') }}" :active="request()->routeIs('chronik')">
            {{ __('Chronik') }}
        </x-nav-link>
        <x-nav-link href="{{ route('arbeitsgruppen') }}" :active="request()->routeIs('arbeitsgruppen')">
            {{ __('Arbeitsgruppen') }}
        </x-nav-link>
        <x-nav-link href="{{ route('ehrenmitglieder') }}" :active="request()->routeIs('ehrenmitglieder')">
            {{ __('Ehrenmitglieder') }}
        </x-nav-link>
        <x-nav-link href="{{ route('termine') }}" :active="request()->routeIs('termine')">
            {{ __('Termine') }}
        </x-nav-link>
        <x-nav-link href="{{ route('satzung') }}" :active="request()->routeIs('satzung')">
            {{ __('Satzung') }}
        </x-nav-link>
        <x-nav-link href="{{ route('mitglied.werden') }}" :active="request()->routeIs('mitglied.werden')">
            {{ __('Mitglied werden') }}
        </x-nav-link>
        <x-nav-link href="{{ route('changelog') }}" :active="request()->routeIs('changelog')">
            {{ __('Changelog') }}
        </x-nav-link>
        <x-nav-link href="{{ route('login') }}" :active="request()->routeIs('login')">
            {{ __('Login') }}
        </x-nav-link>
    @endguest
</div>
        </div>
            @auth
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <!-- Settings Dropdown -->
                <div class="ms-3 relative">
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
                                <button class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition">
                                    <img class="size-8 rounded-full object-cover" src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->name }}" />
                                </button>
                            @else
                                <span class="inline-flex rounded-md">
                                    <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none focus:bg-gray-50 dark:focus:bg-gray-700 active:bg-gray-50 dark:active:bg-gray-700 transition ease-in-out duration-150">
                                        {{ Auth::user()->name }}

                                        <svg class="ms-2 -me-0.5 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </button>
                                </span>
                            @endif
                        </x-slot>

                        <x-slot name="content">
                            <!-- Account Management -->
                            <div class="block px-4 py-2 text-xs text-gray-400">
                                {{ __('Account verwalten') }}
                            </div>

                            <x-dropdown-link href="{{ route('profile.show') }}">
                                {{ __('Profil') }}
                            </x-dropdown-link>

                            @if (Laravel\Jetstream\Jetstream::hasApiFeatures())
                                <x-dropdown-link href="{{ route('api-tokens.index') }}">
                                    {{ __('API Tokens') }}
                                </x-dropdown-link>
                            @endif

                            <div class="border-t border-gray-200 dark:border-gray-600"></div>

                            <!-- Authentication -->
                            <form method="POST" action="{{ route('logout') }}" x-data>
                                @csrf

                                <x-dropdown-link href="{{ route('logout') }}"
                                         @click.prevent="$root.submit();">
                                    {{ __('Ausloggen') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </div>
            </div>
            @endauth

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-900 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-900 focus:text-gray-500 dark:focus:text-gray-400 transition duration-150 ease-in-out">
                    <svg class="size-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            @auth
                <x-responsive-nav-link href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')">
                    {{ __('Dashboard') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('mitglieder.index') }}" :active="request()->routeIs('mitglieder.index')">
                    {{ __('Mitgliederliste') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('mitglieder.karte') }}" :active="request()->routeIs('mitglieder.karte')">
                    {{ __('Mitgliederkarte') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('protokolle') }}" :active="request()->routeIs('protokolle')">
                    {{ __('Protokolle') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('fotogalerie') }}" :active="request()->routeIs('fotogalerie')">
                    {{ __('Fotos') }}
                </x-responsive-nav-link>
            @endauth
        
            @guest
                <!--<x-responsive-nav-link href="{{ route('home') }}" :active="request()->routeIs('home')">
                    {{ __('Startseite') }}
                </x-responsive-nav-link>-->
                <x-responsive-nav-link href="{{ route('chronik') }}" :active="request()->routeIs('chronik')">
                    {{ __('Chronik') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('arbeitsgruppen') }}" :active="request()->routeIs('arbeitsgruppen')">
                    {{ __('Arbeitsgruppen') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('ehrenmitglieder') }}" :active="request()->routeIs('ehrenmitglieder')">
                    {{ __('Ehrenmitglieder') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('termine') }}" :active="request()->routeIs('termine')">
                    {{ __('Termine') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('satzung') }}" :active="request()->routeIs('satzung')">
                    {{ __('Satzung') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('mitglied.werden') }}" :active="request()->routeIs('mitglied.werden')">
                    {{ __('Mitglied werden') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('changelog') }}" :active="request()->routeIs('changelog')">
                    {{ __('Changelog') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('login') }}" :active="request()->routeIs('login')">
                    {{ __('Login') }}
                </x-responsive-nav-link>
            @endguest
        </div>
        
        <!-- Responsive Settings Options -->
        @auth
        <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-600">
            <div class="flex items-center px-4">
                @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
                    <div class="shrink-0 me-3">
                        <img class="size-10 rounded-full object-cover" src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->name }}" />
                    </div>
                @endif

                <div>
                    <div class="font-medium text-base text-gray-800 dark:text-gray-200">{{ Auth::user()->name }}</div>
                    <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
                </div>
            </div>

            <div class="mt-3 space-y-1">
                <!-- Account Management -->
                <x-responsive-nav-link href="{{ route('profile.show') }}" :active="request()->routeIs('profile.show')">
                    {{ __('Profil') }}
                </x-responsive-nav-link>

                @if (Laravel\Jetstream\Jetstream::hasApiFeatures())
                    <x-responsive-nav-link href="{{ route('api-tokens.index') }}" :active="request()->routeIs('api-tokens.index')">
                        {{ __('API Tokens') }}
                    </x-responsive-nav-link>
                @endif

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}" x-data>
                    @csrf

                    <x-responsive-nav-link href="{{ route('logout') }}"
                                   @click.prevent="$root.submit();">
                        {{ __('Ausloggen') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
        @endauth
    </div>
</nav>
