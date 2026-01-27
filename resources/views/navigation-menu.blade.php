<nav x-data="{
        open: false,
        openMenu: null,
        init() {
            this.$watch('open', (isOpen) => {
                if (!this.$refs.mobileToggle) {
                    return;
                }

                const expanded = isOpen ? 'true' : 'false';
                const label = isOpen ? 'Menü schließen' : 'Menü öffnen';

                this.$refs.mobileToggle.setAttribute('aria-expanded', expanded);
                this.$refs.mobileToggle.setAttribute('aria-label', label);
            });

            this.$nextTick(() => this.updateMobileToggleAccessibility());
        },
        updateMobileToggleAccessibility() {
            if (!this.$refs.mobileToggle) {
                return;
            }

            const expanded = this.open ? 'true' : 'false';
            const label = this.open ? 'Menü schließen' : 'Menü öffnen';

            this.$refs.mobileToggle.setAttribute('aria-expanded', expanded);
            this.$refs.mobileToggle.setAttribute('aria-label', label);
        }
    }"
    class="bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700 xl:fixed xl:top-0 xl:left-0 xl:right-0 xl:z-50 lg:shadow-md dark:lg:shadow-none">
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
                        <x-nav-link href="{{ route('fantreffen.2026') }}">Fantreffen 2026</x-nav-link>
                        @if(($showActivePollForAuth ?? false) && ($activePollMenuLabel ?? null))
                            <x-nav-link href="{{ route('umfrage.aktuell') }}">{{ $activePollMenuLabel }}</x-nav-link>
                        @endif
                        <!-- Dropdown Verein -->
                        <div class="relative flex items-center ml-4 group" x-data="{ open: false }" @click="open = !open" @click.away="open = false" @keydown.escape="open = false">
                            <button id="verein-button" type="button" class="px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition" :aria-expanded="open" aria-controls="verein-menu" @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open">
                                Verein
                            </button>
                            <div id="verein-menu" x-show="open" x-cloak class="absolute left-0 top-full mt-px w-48 bg-white dark:bg-gray-700 rounded-md shadow-lg z-50 py-2 group-hover:block" role="menu">
                                <x-dropdown-link href="{{ route('fanfiction.index') }}">Fanfiction</x-dropdown-link>
                                <x-dropdown-link href="{{ route('mitglieder.index') }}">Mitgliederliste</x-dropdown-link>
                                <x-dropdown-link href="{{ route('mitglieder.karte') }}">Mitgliederkarte</x-dropdown-link>
                                <x-dropdown-link href="{{ route('protokolle') }}">Protokolle</x-dropdown-link>
                                <x-dropdown-link href="{{ route('satzung') }}">Satzung</x-dropdown-link>
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
                        @if(Auth::user()->teams()->where('personal_team', false)->exists() || Auth::user()->hasVorstandRole())
                        <div class="relative flex items-center ml-4 group" x-data="{ open: false }" @click="open = !open" @click.away="open = false" @keydown.escape="open = false">
                            <button id="ag-button" type="button" class="px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition" :aria-expanded="open" aria-controls="ag-menu" @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open">
                                AG
                            </button>
                            <div id="ag-menu" x-show="open" x-cloak class="absolute left-0 top-full mt-px w-48 bg-white dark:bg-gray-700 rounded-md shadow-lg z-50 py-2 group-hover:block" role="menu">
                                @if(Auth::user()->hasVorstandRole() || Auth::user()->isMemberOfTeam('AG Fanhörbücher'))
                                    <x-dropdown-link href="{{ route('hoerbuecher.index') }}">EARDRAX Dashboard</x-dropdown-link>
                                @endif
                                @if(Auth::user()->ownedTeams()->where('personal_team', false)->exists())
                                    <x-dropdown-link href="{{ route('ag.index') }}">AG verwalten</x-dropdown-link>
                                @endif
                            </div>
                        </div>
                        @endif
                        @if(Auth::user()->hasAnyRole(\App\Enums\Role::Admin, \App\Enums\Role::Vorstand, \App\Enums\Role::Kassenwart))
                        <div class="relative flex items-center ml-4 group" x-data="{ open: false }" @click="open = !open" @click.away="open = false" @keydown.escape="open = false">
                            <button id="vorstand-button" type="button" class="px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition" :aria-expanded="open" aria-controls="vorstand-menu" @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open">
                                Vorstand
                            </button>
                            <div id="vorstand-menu" x-show="open" x-cloak class="absolute left-0 top-full mt-px w-48 bg-white dark:bg-gray-700 rounded-md shadow-lg z-50 py-2 group-hover:block" role="menu" aria-labelledby="vorstand-button">
                                <x-dropdown-link href="{{ route('admin.statistiken.index') }}">Statistik</x-dropdown-link>
                                <x-dropdown-link href="{{ route('admin.fantreffen.2026') }}">Anmeldungen FT</x-dropdown-link>
                                <x-dropdown-link href="{{ route('admin.fanfiction.index') }}">Fanfiction</x-dropdown-link>
                                @can('manage', \App\Models\Poll::class)
                                    <x-dropdown-link href="{{ route('admin.umfragen.index') }}">Umfrage verwalten</x-dropdown-link>
                                @endcan
                            </div>
                        </div>
                        @endif
                        @if(Auth::user()->hasRole(\App\Enums\Role::Admin))
                        <div class="relative flex items-center ml-4 group" x-data="{ open: false }" @click="open = !open" @click.away="open = false" @keydown.escape="open = false">
                            <button id="admin-button" type="button" class="px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition" :aria-expanded="open" aria-controls="admin-menu" @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open">
                                Admin
                            </button>
                            <div id="admin-menu" x-show="open" x-cloak class="absolute left-0 top-full mt-px w-48 bg-white dark:bg-gray-700 rounded-md shadow-lg z-50 py-2 group-hover:block" role="menu" aria-labelledby="admin-button">
                                <x-dropdown-link href="{{ route('newsletter.create') }}">Newsletter versenden</x-dropdown-link>
                                <x-dropdown-link href="{{ route('admin.messages.index') }}">Kurznachrichten</x-dropdown-link>
                                <x-dropdown-link href="{{ route('rpg.char-editor') }}">Charakter-Editor</x-dropdown-link>
                                <x-dropdown-link href="{{ route('arbeitsgruppen.index') }}">Arbeitsgruppen</x-dropdown-link>
                            </div>
                        </div>
                        @endif
                    @endauth
                    @guest
                        <x-nav-link href="{{ route('fantreffen.2026') }}">Fantreffen 2026</x-nav-link>
                        @if(($showActivePollForGuest ?? false) && ($activePollMenuLabel ?? null))
                            <x-nav-link href="{{ route('umfrage.aktuell') }}">{{ $activePollMenuLabel }}</x-nav-link>
                        @endif
                        <x-nav-link href="{{ route('chronik') }}">Chronik</x-nav-link>
                        <x-nav-link href="{{ route('ehrenmitglieder') }}">Ehrenmitglieder</x-nav-link>
                        <x-nav-link href="{{ route('termine') }}">Termine</x-nav-link>
                        <x-nav-link href="{{ route('arbeitsgruppen') }}">Arbeitsgruppen</x-nav-link>
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
                <button
                    type="button"
                    x-ref="mobileToggle"
                    @click="open = !open"
                    aria-controls="mobile-navigation"
                    aria-expanded="false"
                    class="inline-flex items-center justify-center gap-2 p-2 rounded-md text-gray-400 dark:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                >
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" aria-hidden="true">
                        <path :class="{'hidden': open, 'inline-flex': !open}" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        <path :class="{'hidden': !open, 'inline-flex': open}" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    <span class="text-sm font-medium text-gray-600 dark:text-gray-300" x-text="open ? 'Menü schließen' : 'Menü öffnen'">Menü öffnen</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile-Menü -->
    <div id="mobile-navigation" :class="{'block': open, 'hidden': !open}" class="hidden sm:hidden">
        @auth
            <x-responsive-nav-link href="{{ route('dashboard') }}">Dashboard</x-responsive-nav-link>
            <x-responsive-nav-link href="{{ route('fantreffen.2026') }}">Fantreffen 2026</x-responsive-nav-link>
            @if(($showActivePollForAuth ?? false) && ($activePollMenuLabel ?? null))
                <x-responsive-nav-link href="{{ route('umfrage.aktuell') }}">{{ $activePollMenuLabel }}</x-responsive-nav-link>
            @endif
            <button id="verein-mobile-button" type="button" @click="openMenu = (openMenu === 'verein' ? null : 'verein')" class="w-full text-left px-4 py-2 font-bold text-gray-600 dark:text-gray-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500" :class="{ 'bg-gray-100 dark:bg-gray-700': openMenu === 'verein' }" :aria-expanded="openMenu === 'verein'" aria-controls="verein-mobile-menu" @keydown.enter.prevent="openMenu = (openMenu === 'verein' ? null : 'verein')" @keydown.space.prevent="openMenu = (openMenu === 'verein' ? null : 'verein')">
            Verein</button>
            <div id="verein-mobile-menu" x-show="openMenu === 'verein'" x-cloak class="italic">
                <x-responsive-nav-link href="{{ route('fanfiction.index') }}">Fanfiction</x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('mitglieder.index') }}">Mitgliederliste</x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('mitglieder.karte') }}">Mitgliederkarte</x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('protokolle') }}">Protokolle</x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('satzung') }}">Satzung</x-responsive-nav-link>
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
            @if(Auth::user()->teams()->where('personal_team', false)->exists() || Auth::user()->hasVorstandRole())
            <button id="ag-mobile-button" type="button" @click="openMenu = (openMenu === 'ag' ? null : 'ag')" class="w-full text-left px-4 py-2 font-bold text-gray-600 dark:text-gray-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500" :class="{ 'bg-gray-100 dark:bg-gray-700': openMenu === 'ag' }" :aria-expanded="openMenu === 'ag'" aria-controls="ag-mobile-menu" @keydown.enter.prevent="openMenu = (openMenu === 'ag' ? null : 'ag')" @keydown.space.prevent="openMenu = (openMenu === 'ag' ? null : 'ag')">
            AG</button>
            <div id="ag-mobile-menu" x-show="openMenu === 'ag'" x-cloak class="italic">
                @if(Auth::user()->hasVorstandRole() || Auth::user()->isMemberOfTeam('AG Fanhörbücher'))
                    <x-responsive-nav-link href="{{ route('hoerbuecher.index') }}">EARDRAX Dashboard</x-responsive-nav-link>
                @endif
                @if(Auth::user()->ownedTeams()->where('personal_team', false)->exists())
                    <x-responsive-nav-link href="{{ route('ag.index') }}">AG verwalten</x-responsive-nav-link>
                @endif
            </div>
            @endif
            @if(Auth::user()->hasAnyRole(\App\Enums\Role::Admin, \App\Enums\Role::Vorstand, \App\Enums\Role::Kassenwart))
            <button id="vorstand-mobile-button" type="button" @click="openMenu = (openMenu === 'vorstand' ? null : 'vorstand')" class="w-full text-left px-4 py-2 font-bold text-gray-600 dark:text-gray-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500" :class="{ 'bg-gray-100 dark:bg-gray-700': openMenu === 'vorstand' }" :aria-expanded="openMenu === 'vorstand'" aria-controls="vorstand-mobile-menu" @keydown.enter.prevent="openMenu = (openMenu === 'vorstand' ? null : 'vorstand')" @keydown.space.prevent="openMenu = (openMenu === 'vorstand' ? null : 'vorstand')">
            Vorstand</button>
            <div id="vorstand-mobile-menu" x-show="openMenu === 'vorstand'" x-cloak class="italic" aria-labelledby="vorstand-mobile-button">
                <x-responsive-nav-link href="{{ route('admin.statistiken.index') }}">Statistik</x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('admin.fantreffen.2026') }}">Anmeldungen FT</x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('admin.fanfiction.index') }}">Fanfiction</x-responsive-nav-link>
                @can('manage', \App\Models\Poll::class)
                    <x-responsive-nav-link href="{{ route('admin.umfragen.index') }}">Umfrage verwalten</x-responsive-nav-link>
                @endcan
            </div>
            @endif
            @if(Auth::user()->hasRole(\App\Enums\Role::Admin))
            <button id="admin-mobile-button" type="button" @click="openMenu = (openMenu === 'admin' ? null : 'admin')" class="w-full text-left px-4 py-2 font-bold text-gray-600 dark:text-gray-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500" :class="{ 'bg-gray-100 dark:bg-gray-700': openMenu === 'admin' }" :aria-expanded="openMenu === 'admin'" aria-controls="admin-mobile-menu" @keydown.enter.prevent="openMenu = (openMenu === 'admin' ? null : 'admin')" @keydown.space.prevent="openMenu = (openMenu === 'admin' ? null : 'admin')">
            Admin</button>
            <div id="admin-mobile-menu" x-show="openMenu === 'admin'" x-cloak class="italic" aria-labelledby="admin-mobile-button">
                <x-responsive-nav-link href="{{ route('newsletter.create') }}">Newsletter versenden</x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('admin.messages.index') }}">Kurznachrichten</x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('rpg.char-editor') }}">Charakter-Editor</x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('arbeitsgruppen.index') }}">Arbeitsgruppen</x-responsive-nav-link>
            </div>
            @endif

        @endauth

        @guest
            <x-responsive-nav-link href="{{ route('fantreffen.2026') }}">Fantreffen 2026</x-responsive-nav-link>
            @if(($showActivePollForGuest ?? false) && ($activePollMenuLabel ?? null))
                <x-responsive-nav-link href="{{ route('umfrage.aktuell') }}">{{ $activePollMenuLabel }}</x-responsive-nav-link>
            @endif
            <x-responsive-nav-link href="{{ route('chronik') }}">Chronik</x-responsive-nav-link>
            <x-responsive-nav-link href="{{ route('ehrenmitglieder') }}">Ehrenmitglieder</x-responsive-nav-link>
            <x-responsive-nav-link href="{{ route('termine') }}">Termine</x-responsive-nav-link>
            <x-responsive-nav-link href="{{ route('arbeitsgruppen') }}">Arbeitsgruppen</x-responsive-nav-link>
            <x-responsive-nav-link href="{{ route('satzung') }}">Satzung</x-responsive-nav-link>
            <x-responsive-nav-link href="{{ route('mitglied.werden') }}">Mitglied werden</x-responsive-nav-link>
            <x-responsive-nav-link href="{{ route('spenden') }}">Spenden</x-responsive-nav-link>
            <x-responsive-nav-link href="{{ route('changelog') }}">Changelog</x-responsive-nav-link>
            <x-responsive-nav-link href="{{ route('login') }}">Login</x-responsive-nav-link>
        @endguest
    </div>
</nav>
