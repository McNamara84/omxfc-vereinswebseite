<nav x-data="{ open: false, openMenu: null }" class="bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700 xl:fixed xl:top-0 xl:left-0 xl:right-0 xl:z-50 lg:shadow-md dark:lg:shadow-none">
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
                        <div class="relative flex items-center ml-4 group" x-data="{ open: false }" @click="open = !open" @click.away="open = false" @keydown.escape="open = false">
                            <button id="verein-button" type="button" class="px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition" :aria-expanded="open" aria-controls="verein-menu" @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open">
                                Verein
                            </button>
                            <div id="verein-menu" x-show="open" x-cloak class="absolute left-0 top-full mt-px w-48 bg-white dark:bg-gray-700 rounded-md shadow-lg z-50 py-2 group-hover:block" role="menu">
                                <x-dropdown-link href="{{ route('mitglieder.index') }}">Mitgliederliste</x-dropdown-link>
                                <x-dropdown-link href="{{ route('mitglieder.karte') }}">Mitgliederkarte</x-dropdown-link>
                                <x-dropdown-link href="{{ route('protokolle') }}">Protokolle</x-dropdown-link>
                                <x-dropdown-link href="{{ route('kassenbuch.index') }}">Kassenbuch</x-dropdown-link>
                                <x-dropdown-link href="{{ route('reviews.index') }}">Rezensionen</x-dropdown-link>
                                <x-dropdown-link href="{{ route('romantausch.index') }}">Tauschbörse</x-dropdown-link>
                            </div>
                        </div>
                        <!-- Dropdown Veranstaltungen -->
                        <div class="relative flex items-center ml-4 group" x-data="{ open: false }" @click="open = !open" @click.away="open = false" @keydown.escape="open = false">
                            <button id="veranstaltungen-button" type="button" class="px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition" :aria-expanded="open" aria-controls="veranstaltungen-menu" @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open">
                                Veranstaltungen
                            </button>
                            <div id="veranstaltungen-menu" x-show="open" x-cloak class="absolute left-0 top-full mt-px w-48 bg-white dark:bg-gray-700 rounded-md shadow-lg z-50 py-2 group-hover:block" role="menu">
                                <x-dropdown-link href="{{ route('fotogalerie') }}">Fotos</x-dropdown-link>
                                <x-dropdown-link href="{{ route('meetings') }}">Meetings</x-dropdown-link>
                                <x-dropdown-link href="{{ route('termine') }}">Termine</x-dropdown-link>
                            </div>
                        </div>
                        <div class="relative flex items-center ml-4 group" x-data="{ open: false }" @click="open = !open" @click.away="open = false" @keydown.escape="open = false">
                            <button id="baxx-button" type="button" class="px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition" :aria-expanded="open" aria-controls="baxx-menu" @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open">
                                Baxx
                            </button>
                            <div id="baxx-menu" x-show="open" x-cloak class="absolute left-0 top-full mt-px w-48 bg-white dark:bg-gray-700 rounded-md shadow-lg z-50 py-2 group-hover:block" role="menu">
                                <x-dropdown-link href="{{ route('todos.index') }}">Challenges</x-dropdown-link>
                                <x-dropdown-link href="{{ route('rewards.index') }}">Belohnungen</x-dropdown-link>
                            </div>
                        </div>
                        <!-- Dropdown Veranstaltungen -->
                        <div class="relative flex items-center ml-4 group" x-data="{ open: false }" @click="open = !open" @click.away="open = false" @keydown.escape="open = false">
                            <button id="belohnungen-button" type="button" class="px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition" :aria-expanded="open" aria-controls="belohnungen-menu" @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open">
                                Belohnungen
                            </button>
                            <div id="belohnungen-menu" x-show="open" x-cloak class="absolute left-0 top-full mt-px w-48 bg-white dark:bg-gray-700 rounded-md shadow-lg z-50 py-2 group-hover:block" role="menu">
                                <x-dropdown-link href="{{ route('maddraxiversum.index') }}">Maddraxiversum</x-dropdown-link>
                                <x-dropdown-link href="{{ route('downloads') }}">Downloads</x-dropdown-link>
                                <x-dropdown-link href="{{ route('kompendium.index') }}">Kompendium</x-dropdown-link>
                                <x-dropdown-link href="{{ route('statistik.index') }}">Statistik</x-dropdown-link>
                            </div>
                        </div>
                        @if(Auth::user()->currentTeam && Auth::user()->currentTeam->hasUserWithRole(Auth::user(), 'Admin'))
                        <div class="relative flex items-center ml-4 group" x-data="{ open: false }" @click="open = !open" @click.away="open = false" @keydown.escape="open = false">
                            <button id="vorstand-button" type="button" class="px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition" :aria-expanded="open" aria-controls="vorstand-menu" @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open">
                                Vorstand
                            </button>
                            <div id="vorstand-menu" x-show="open" x-cloak class="absolute left-0 top-full mt-px w-48 bg-white dark:bg-gray-700 rounded-md shadow-lg z-50 py-2 group-hover:block" role="menu" aria-labelledby="vorstand-button">
                                <x-dropdown-link href="{{ route('admin.index') }}">Admin</x-dropdown-link>
                                <x-dropdown-link href="{{ route('hoerbuecher.index') }}">EARDRAX Dashboard</x-dropdown-link>
                                <x-dropdown-link href="{{ route('newsletter.create') }}">Newsletter versenden</x-dropdown-link>
                                <x-dropdown-link href="{{ route('arbeitsgruppen.create') }}">Neue AG</x-dropdown-link>
                            </div>
                        </div>
                        @endif
                    @endauth
                    @guest
                        <x-nav-link href="{{ route('chronik') }}">Chronik</x-nav-link>
                        <x-nav-link href="{{ route('arbeitsgruppen') }}">Arbeitsgruppen</x-nav-link>
                        <x-nav-link href="{{ route('ehrenmitglieder') }}">Ehrenmitglieder</x-nav-link>
                        <x-nav-link href="{{ route('termine') }}">Termine</x-nav-link>
                        <x-nav-link href="{{ route('satzung') }}">Satzung</x-nav-link>
                        <x-nav-link href="{{ route('mitglied.werden') }}">Mitglied werden</x-nav-link>
                        <x-nav-link href="{{ route('spenden') }}">Spenden</x-nav-link>
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
            <button id="verein-mobile-button" type="button" @click="openMenu = (openMenu === 'verein' ? null : 'verein')" class="w-full text-left px-4 py-2 font-bold text-gray-600 dark:text-gray-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500" :class="{ 'bg-gray-100 dark:bg-gray-700': openMenu === 'verein' }" :aria-expanded="openMenu === 'verein'" aria-controls="verein-mobile-menu" @keydown.enter.prevent="openMenu = (openMenu === 'verein' ? null : 'verein')" @keydown.space.prevent="openMenu = (openMenu === 'verein' ? null : 'verein')">
            Verein</button>
            <div id="verein-mobile-menu" x-show="openMenu === 'verein'" x-cloak class="italic">
                <x-responsive-nav-link href="{{ route('mitglieder.index') }}">Mitgliederliste</x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('mitglieder.karte') }}">Mitgliederkarte</x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('protokolle') }}">Protokolle</x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('kassenbuch.index') }}">Kassenbuch</x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('reviews.index') }}">Rezensionen</x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('romantausch.index') }}">Tauschbörse</x-responsive-nav-link>
            </div>

            <button id="veranstaltungen-mobile-button" type="button" @click="openMenu = (openMenu === 'veranstaltungen' ? null : 'veranstaltungen')" class="w-full text-left px-4 py-2 font-bold text-gray-600 dark:text-gray-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500" :class="{ 'bg-gray-100 dark:bg-gray-700': openMenu === 'veranstaltungen' }" :aria-expanded="openMenu === 'veranstaltungen'" aria-controls="veranstaltungen-mobile-menu" @keydown.enter.prevent="openMenu = (openMenu === 'veranstaltungen' ? null : 'veranstaltungen')" @keydown.space.prevent="openMenu = (openMenu === 'veranstaltungen' ? null : 'veranstaltungen')">
            Veranstaltungen</button>
            <div id="veranstaltungen-mobile-menu" x-show="openMenu === 'veranstaltungen'" x-cloak class="italic">
                <x-responsive-nav-link href="{{ route('fotogalerie') }}">Fotos</x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('meetings') }}">Meetings</x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('termine') }}">Termine</x-responsive-nav-link>
            </div>

            <button id="baxx-mobile-button" type="button" @click="openMenu = (openMenu === 'baxx' ? null : 'baxx')" class="w-full text-left px-4 py-2 font-bold text-gray-600 dark:text-gray-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500" :class="{ 'bg-gray-100 dark:bg-gray-700': openMenu === 'baxx' }" :aria-expanded="openMenu === 'baxx'" aria-controls="baxx-mobile-menu" @keydown.enter.prevent="openMenu = (openMenu === 'baxx' ? null : 'baxx')" @keydown.space.prevent="openMenu = (openMenu === 'baxx' ? null : 'baxx')">
            Baxx</button>
            <div id="baxx-mobile-menu" x-show="openMenu === 'baxx'" x-cloak class="italic">
                <x-responsive-nav-link href="{{ route('todos.index') }}">Challenges</x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('rewards.index') }}">Belohnungen</x-responsive-nav-link>
            </div>
            <button id="belohnungen-mobile-button" type="button" @click="openMenu = (openMenu === 'belohnungen' ? null : 'belohnungen')" class="w-full text-left px-4 py-2 font-bold text-gray-600 dark:text-gray-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500" :class="{ 'bg-gray-100 dark:bg-gray-700': openMenu === 'belohnungen' }" :aria-expanded="openMenu === 'belohnungen'" aria-controls="belohnungen-mobile-menu" @keydown.enter.prevent="openMenu = (openMenu === 'belohnungen' ? null : 'belohnungen')" @keydown.space.prevent="openMenu = (openMenu === 'belohnungen' ? null : 'belohnungen')">
            Belohnungen</button>
            <div id="belohnungen-mobile-menu" x-show="openMenu === 'belohnungen'" x-cloak class="italic">
                <x-responsive-nav-link href="{{ route('maddraxiversum.index') }}">Maddraxiversum</x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('downloads') }}">Downloads</x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('kompendium.index') }}">Kompendium</x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('statistik.index') }}">Statistik</x-responsive-nav-link>
            </div>
            @if(Auth::user()->currentTeam && Auth::user()->currentTeam->hasUserWithRole(Auth::user(), 'Admin'))
            <button id="vorstand-mobile-button" type="button" @click="openMenu = (openMenu === 'vorstand' ? null : 'vorstand')" class="w-full text-left px-4 py-2 font-bold text-gray-600 dark:text-gray-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500" :class="{ 'bg-gray-100 dark:bg-gray-700': openMenu === 'vorstand' }" :aria-expanded="openMenu === 'vorstand'" aria-controls="vorstand-mobile-menu" @keydown.enter.prevent="openMenu = (openMenu === 'vorstand' ? null : 'vorstand')" @keydown.space.prevent="openMenu = (openMenu === 'vorstand' ? null : 'vorstand')">
            Vorstand</button>
            <div id="vorstand-mobile-menu" x-show="openMenu === 'vorstand'" x-cloak class="italic" aria-labelledby="vorstand-mobile-button">
                <x-responsive-nav-link href="{{ route('admin.index') }}">Admin</x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('hoerbuecher.index') }}">EARDRAX Dashboard</x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('newsletter.create') }}">Newsletter versenden</x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('arbeitsgruppen.create') }}">Neue AG</x-responsive-nav-link>
            </div>
            @endif

        @endauth

        @guest
            <x-responsive-nav-link href="{{ route('chronik') }}">Chronik</x-responsive-nav-link>
            <x-responsive-nav-link href="{{ route('arbeitsgruppen') }}">Arbeitsgruppen</x-responsive-nav-link>
            <x-responsive-nav-link href="{{ route('ehrenmitglieder') }}">Ehrenmitglieder</x-responsive-nav-link>
            <x-responsive-nav-link href="{{ route('termine') }}">Termine</x-responsive-nav-link>
            <x-responsive-nav-link href="{{ route('satzung') }}">Satzung</x-responsive-nav-link>
            <x-responsive-nav-link href="{{ route('mitglied.werden') }}">Mitglied werden</x-responsive-nav-link>
            <x-responsive-nav-link href="{{ route('spenden') }}">Spenden</x-responsive-nav-link>
            <x-responsive-nav-link href="{{ route('changelog') }}">Changelog</x-responsive-nav-link>
            <x-responsive-nav-link href="{{ route('login') }}">Login</x-responsive-nav-link>
        @endguest
    </div>
</nav>
