<nav x-data="{ open: false, openMenu: null }" class="bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <!-- Linker Bereich: Logo + Hauptmenü -->
            <div class="flex">
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('home') }}">
                        <x-application-mark class="block h-9 w-auto" />
                    </a>
                </div>

                <div class="hidden sm:ml-10 sm:flex sm:space-x-8">
                    @auth
                        <x-nav-link href="{{ route('dashboard') }}">Dashboard</x-nav-link>
                        <!-- Dropdown Verein -->
                        <div class="relative flex items-center ml-4 group" x-data="{ open: false }" @click="open = !open" @click.away="open = false">
                            <button class="px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white focus:outline-none transition">
                                Verein
                            </button>
                            <div x-show="open" x-cloak class="absolute left-0 top-full mt-px w-48 bg-white dark:bg-gray-700 rounded-md shadow-lg z-50 py-2 group-hover:block">
                                <x-dropdown-link href="{{ route('mitglieder.index') }}">Mitgliederliste</x-dropdown-link>
                                <x-dropdown-link href="{{ route('mitglieder.karte') }}">Mitgliederkarte</x-dropdown-link>
                                <x-dropdown-link href="{{ route('protokolle') }}">Protokolle</x-dropdown-link>
                                <x-dropdown-link href="{{ route('kassenbuch.index') }}">Kassenbuch</x-dropdown-link>
                            </div>
                        </div>
                        <!-- Dropdown Veranstaltungen -->
                        <div class="relative flex items-center ml-4 group" x-data="{ open: false }" @click="open = !open" @click.away="open = false">
                            <button class="px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white focus:outline-none transition">
                                Veranstaltungen
                            </button>
                            <div x-show="open" x-cloak class="absolute left-0 top-full mt-px w-48 bg-white dark:bg-gray-700 rounded-md shadow-lg z-50 py-2 group-hover:block">
                                <x-dropdown-link href="{{ route('fotogalerie') }}">Fotos</x-dropdown-link>
                                <x-dropdown-link href="{{ route('meetings') }}">Meetings</x-dropdown-link>
                            </div>
                        </div>
                        <x-nav-link href="{{ route('todos.index') }}">Challenges</x-nav-link>
                        <!-- Dropdown Veranstaltungen -->
                        <div class="relative flex items-center ml-4 group" x-data="{ open: false }" @click="open = !open" @click.away="open = false">
                            <button class="px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white focus:outline-none transition">
                                Mitgliedsvorteile
                            </button>
                            <div x-show="open" x-cloak class="absolute left-0 top-full mt-px w-48 bg-white dark:bg-gray-700 rounded-md shadow-lg z-50 py-2 group-hover:block">
                                <x-dropdown-link href="{{ route('maddraxiversum.index') }}">Maddraxiversum</x-dropdown-link>
                                <x-dropdown-link href="{{ route('romantausch.index') }}">Tauschbörse</x-dropdown-link>
                                <x-dropdown-link href="{{ route('downloads') }}">Downloads</x-dropdown-link>
                                <x-dropdown-link href="{{ route('kompendium.index') }}">Kompendium</x-dropdown-link>
                                <x-dropdown-link href="{{ route('statistik.index') }}">Statistik</x-dropdown-link>
                                <x-dropdown-link href="{{ route('reviews.index') }}">Rezensionen</x-dropdown-link>
                            </div>
                        </div>
                    @endauth
                    @guest
                        <x-nav-link href="{{ route('chronik') }}">Chronik</x-nav-link>
                        <x-nav-link href="{{ route('arbeitsgruppen') }}">Arbeitsgruppen</x-nav-link>
                        <x-nav-link href="{{ route('ehrenmitglieder') }}">Ehrenmitglieder</x-nav-link>
                        <x-nav-link href="{{ route('termine') }}">Termine</x-nav-link>
                        <x-nav-link href="{{ route('satzung') }}">Satzung</x-nav-link>
                        <x-nav-link href="{{ route('mitglied.werden') }}">Mitglied werden</x-nav-link>
                        <x-nav-link href="{{ route('changelog') }}">Changelog</x-nav-link>
                    @endguest
                </div>
            </div>

            <!-- Rechter Bereich (nur Login bzw. Usermenü) -->
            <div class="hidden sm:flex sm:items-center">
                @auth
                    <x-dropdown align="right">
                        <x-slot name="trigger">
                            <button class="flex items-center">
                                <img class="h-8 w-8 rounded-full" src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->name }}" />
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            <x-dropdown-link href="{{ route('profile.show') }}">Profil</x-dropdown-link>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link href="{{ route('logout') }}" onclick="event.preventDefault(); this.closest('form').submit();">
                                    Ausloggen
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                @endauth

                @guest
                    <x-nav-link href="{{ route('login') }}">Login</x-nav-link>
                @endguest
            </div>

            <!-- Hamburger (Mobile) -->
            <div class="-mr-2 flex items-center sm:hidden">
                <button @click="open = !open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 dark:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none">
                        <path :class="{'hidden': open, 'inline-flex': !open}" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        <path :class="{'hidden': !open, 'inline-flex': open}" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile-Menü -->
    <div :class="{'block': open, 'hidden': !open}" class="hidden sm:hidden">
        @auth
            <x-responsive-nav-link href="{{ route('dashboard') }}">Dashboard</x-responsive-nav-link>
            <button @click="openMenu = (openMenu === 'verein' ? null : 'verein')" class="w-full text-left px-4 py-2 font-semibold text-gray-600 dark:text-gray-300">Verein</button>
            <div x-show="openMenu === 'verein'" x-cloak>
                <x-responsive-nav-link href="{{ route('mitglieder.index') }}">Mitgliederliste</x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('mitglieder.karte') }}">Mitgliederkarte</x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('protokolle') }}">Protokolle</x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('kassenbuch.index') }}">Kassenbuch</x-responsive-nav-link>
            </div>

            <button @click="openMenu = (openMenu === 'veranstaltungen' ? null : 'veranstaltungen')" class="w-full text-left px-4 py-2 font-semibold text-gray-600 dark:text-gray-300">Veranstaltungen</button>
            <div x-show="openMenu === 'veranstaltungen'" x-cloak>
                <x-responsive-nav-link href="{{ route('fotogalerie') }}">Fotos</x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('meetings') }}">Meetings</x-responsive-nav-link>
            </div>

            <x-responsive-nav-link href="{{ route('todos.index') }}">Challenges</x-responsive-nav-link>
            <x-responsive-nav-link href="{{ route('maddraxiversum.index') }}">Maddraxiversum</x-responsive-nav-link>
            <x-responsive-nav-link href="{{ route('romantausch.index') }}">Tauschbörse</x-responsive-nav-link>
            <x-responsive-nav-link href="{{ route('downloads') }}">Downloads</x-responsive-nav-link>
            <x-responsive-nav-link href="{{ route('kompendium.index') }}">Kompendium</x-responsive-nav-link>
            <x-responsive-nav-link href="{{ route('statistik.index') }}">Statistik</x-responsive-nav-link>
            <x-responsive-nav-link href="{{ route('reviews.index') }}">Rezensionen</x-responsive-nav-link>
        @endauth

        @guest
            <x-responsive-nav-link href="{{ route('chronik') }}">Chronik</x-responsive-nav-link>
            <x-responsive-nav-link href="{{ route('arbeitsgruppen') }}">Arbeitsgruppen</x-responsive-nav-link>
            <x-responsive-nav-link href="{{ route('ehrenmitglieder') }}">Ehrenmitglieder</x-responsive-nav-link>
            <x-responsive-nav-link href="{{ route('termine') }}">Termine</x-responsive-nav-link>
            <x-responsive-nav-link href="{{ route('satzung') }}">Satzung</x-responsive-nav-link>
            <x-responsive-nav-link href="{{ route('mitglied.werden') }}">Mitglied werden</x-responsive-nav-link>
            <x-responsive-nav-link href="{{ route('changelog') }}">Changelog</x-responsive-nav-link>
            <x-responsive-nav-link href="{{ route('login') }}">Login</x-responsive-nav-link>
        @endguest
    </div>
</nav>
