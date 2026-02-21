<x-nav sticky full-width>
    <x-slot:brand>
        <a href="{{ route('home') }}" class="shrink-0">
            <x-application-mark class="block h-9 w-auto" />
        </a>

        {{-- Desktop-Menü --}}
        <div class="hidden flex-1 justify-between xl:flex xl:items-center">
            @auth
                <x-button label="Dashboard" link="{{ route('dashboard') }}" class="btn-ghost btn-sm flex-1" />
                <x-button label="Fantreffen 2026" link="{{ route('fantreffen.2026') }}" class="btn-ghost btn-sm flex-1" />
                @if(($showActivePollForAuth ?? false) && ($activePollMenuLabel ?? null))
                    <x-button label="{{ $activePollMenuLabel }}" link="{{ route('umfrage.aktuell') }}" class="btn-ghost btn-sm flex-1" />
                @endif

                {{-- Dropdown Verein --}}
                <x-dropdown label="Verein" class="btn-ghost btn-sm flex-1">
                    <x-menu-item title="Fanfiction" link="{{ route('fanfiction.index') }}" />
                    <x-menu-item title="Mitgliederliste" link="{{ route('mitglieder.index') }}" />
                    <x-menu-item title="Mitgliederkarte" link="{{ route('mitglieder.karte') }}" />
                    <x-menu-item title="Protokolle" link="{{ route('protokolle') }}" />
                    <x-menu-item title="Satzung" link="{{ route('satzung') }}" />
                    <x-menu-item title="Kassenbuch" link="{{ route('kassenbuch.index') }}" />
                    <x-menu-item title="Rezensionen" link="{{ route('reviews.index') }}" />
                    <x-menu-item title="Tauschbörse" link="{{ route('romantausch.index') }}" />
                </x-dropdown>

                {{-- Dropdown Veranstaltungen --}}
                <x-dropdown label="Veranstaltungen" class="btn-ghost btn-sm flex-1">
                    <x-menu-item title="Fotos" link="{{ route('fotogalerie') }}" />
                    <x-menu-item title="Meetings" link="{{ route('meetings') }}" />
                    <x-menu-item title="Termine" link="{{ route('termine') }}" />
                </x-dropdown>

                {{-- Dropdown Baxx --}}
                <x-dropdown label="Baxx" class="btn-ghost btn-sm flex-1">
                    <x-menu-item title="Challenges" link="{{ route('todos.index') }}" />
                    <x-menu-item title="Belohnungen" link="{{ route('rewards.index') }}" />
                </x-dropdown>

                {{-- Dropdown Belohnungen --}}
                <x-dropdown label="Belohnungen" class="btn-ghost btn-sm flex-1">
                    <x-menu-item title="Maddraxiversum" link="{{ route('maddraxiversum.index') }}" />
                    <x-menu-item title="Downloads" link="{{ route('downloads') }}" />
                    <x-menu-item title="Kompendium" link="{{ route('kompendium.index') }}" />
                    <x-menu-item title="Statistik" link="{{ route('statistik.index') }}" />
                </x-dropdown>

                {{-- Dropdown AG (nur wenn Mitglied einer AG oder Vorstand) --}}
                @if(Auth::user()->teams()->where('personal_team', false)->exists() || Auth::user()->hasVorstandRole())
                    <x-dropdown label="AG" class="btn-ghost btn-sm flex-1">
                        @if(Auth::user()->hasVorstandRole() || Auth::user()->isMemberOfTeam('AG Fanhörbücher'))
                            <x-menu-item title="EARDRAX Dashboard" link="{{ route('hoerbuecher.index') }}" />
                        @endif
                        @if(Auth::user()->isMemberOfTeam('AG Maddraxikon'))
                            <x-menu-item title="Kompendium" link="{{ route('kompendium.index') }}" />
                        @endif
                        @if(Auth::user()->ownedTeams()->where('personal_team', false)->exists())
                            <x-menu-item title="AG verwalten" link="{{ route('ag.index') }}" />
                        @endif
                    </x-dropdown>
                @endif

                {{-- Dropdown Vorstand (nur Vorstand/Kassenwart/Admin) --}}
                @if(Auth::user()->hasAnyRole(\App\Enums\Role::Admin, \App\Enums\Role::Vorstand, \App\Enums\Role::Kassenwart))
                    <x-dropdown label="Vorstand" class="btn-ghost btn-sm flex-1">
                        <x-menu-item title="Statistik" link="{{ route('admin.statistiken.index') }}" />
                        <x-menu-item title="Anmeldungen FT" link="{{ route('admin.fantreffen.2026') }}" />
                        <x-menu-item title="Fanfiction" link="{{ route('admin.fanfiction.index') }}" />
                        @can('manage', \App\Models\Poll::class)
                            <x-menu-item title="Umfrage verwalten" link="{{ route('admin.umfragen.index') }}" />
                        @endcan
                    </x-dropdown>
                @endif

                {{-- Dropdown Admin (nur Admin) --}}
                @if(Auth::user()->hasRole(\App\Enums\Role::Admin))
                    <x-dropdown label="Admin" class="btn-ghost btn-sm flex-1">
                        <x-menu-item title="Newsletter versenden" link="{{ route('newsletter.create') }}" />
                        <x-menu-item title="Kurznachrichten" link="{{ route('admin.messages.index') }}" />
                        <x-menu-item title="Charakter-Editor" link="{{ route('rpg.char-editor') }}" />
                        <x-menu-item title="Arbeitsgruppen" link="{{ route('arbeitsgruppen.index') }}" />
                    </x-dropdown>
                @endif
            @endauth

            @guest
                <x-button label="Fantreffen 2026" link="{{ route('fantreffen.2026') }}" class="btn-ghost btn-sm flex-1" />
                @if(($showActivePollForGuest ?? false) && ($activePollMenuLabel ?? null))
                    <x-button label="{{ $activePollMenuLabel }}" link="{{ route('umfrage.aktuell') }}" class="btn-ghost btn-sm flex-1" />
                @endif
                <x-button label="Chronik" link="{{ route('chronik') }}" class="btn-ghost btn-sm flex-1" />
                <x-button label="Ehrenmitglieder" link="{{ route('ehrenmitglieder') }}" class="btn-ghost btn-sm flex-1" />
                <x-button label="Termine" link="{{ route('termine') }}" class="btn-ghost btn-sm flex-1" />
                <x-button label="Arbeitsgruppen" link="{{ route('arbeitsgruppen') }}" class="btn-ghost btn-sm flex-1" />
                <x-button label="Satzung" link="{{ route('satzung') }}" class="btn-ghost btn-sm flex-1" />
                <x-button label="Mitglied werden" link="{{ route('mitglied.werden') }}" class="btn-ghost btn-sm flex-1" />
                <x-button label="Spenden" link="{{ route('spenden') }}" class="btn-ghost btn-sm flex-1" />
                <x-button label="Changelog" link="{{ route('changelog') }}" class="btn-ghost btn-sm flex-1" />
            @endguest
        </div>
    </x-slot:brand>

    <x-slot:actions>
        {{-- Theme-Toggle --}}
        <x-theme-toggle darkTheme="coffee" lightTheme="caramellatte" darkClass="dark" lightClass="" class="w-5 h-5" />

        {{-- Profil-Dropdown / Login (Desktop) --}}
        <div class="hidden xl:flex xl:items-center">
            @auth
                <x-dropdown right>
                    <x-slot:trigger>
                        <button class="flex items-center">
                            <img class="h-8 w-8 rounded-full object-cover" src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->name }}" />
                        </button>
                    </x-slot:trigger>
                    <x-menu-item title="Profil" link="{{ route('profile.show') }}" icon="o-user" />
                    <x-menu-separator />
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-menu-item title="Ausloggen" icon="o-arrow-right-on-rectangle" onclick="event.preventDefault(); this.closest('form').submit();" />
                    </form>
                </x-dropdown>
            @endauth

            @guest
                <x-button label="Login" link="{{ route('login') }}" class="btn-ghost btn-sm" />
            @endguest
        </div>

        {{-- Hamburger (Mobile) --}}
        <div class="-mr-2 flex items-center xl:hidden" x-data="{ open: false }">
            <button
                type="button"
                @click="open = !open; $dispatch('toggle-mobile-menu', { open })"
                :aria-expanded="open"
                aria-label="Menü öffnen"
                aria-controls="mobile-navigation"
                class="btn btn-ghost btn-sm"
            >
                <x-icon x-show="!open" name="o-bars-3" class="w-6 h-6" />
                <x-icon x-show="open" x-cloak name="o-x-mark" class="w-6 h-6" />
                <span class="text-sm" x-text="open ? 'Schließen' : 'Menü'">Menü</span>
            </button>
        </div>
    </x-slot:actions>
</x-nav>

{{-- Mobile-Menü --}}
<div id="mobile-navigation"
     x-data="{ mobileOpen: false }"
     @toggle-mobile-menu.window="mobileOpen = $event.detail.open"
     x-show="mobileOpen"
     x-cloak
     x-collapse
     class="xl:hidden bg-base-100 border-b border-base-content/10">
    <x-menu class="p-2">
        @auth
            <x-menu-item title="Dashboard" link="{{ route('dashboard') }}" icon="o-home" />
            <x-menu-item title="Fantreffen 2026" link="{{ route('fantreffen.2026') }}" icon="o-calendar-days" />
            @if(($showActivePollForAuth ?? false) && ($activePollMenuLabel ?? null))
                <x-menu-item title="{{ $activePollMenuLabel }}" link="{{ route('umfrage.aktuell') }}" icon="o-chart-bar" />
            @endif

            <x-menu-sub title="Verein" icon="o-user-group">
                <x-menu-item title="Fanfiction" link="{{ route('fanfiction.index') }}" />
                <x-menu-item title="Mitgliederliste" link="{{ route('mitglieder.index') }}" />
                <x-menu-item title="Mitgliederkarte" link="{{ route('mitglieder.karte') }}" />
                <x-menu-item title="Protokolle" link="{{ route('protokolle') }}" />
                <x-menu-item title="Satzung" link="{{ route('satzung') }}" />
                <x-menu-item title="Kassenbuch" link="{{ route('kassenbuch.index') }}" />
                <x-menu-item title="Rezensionen" link="{{ route('reviews.index') }}" />
                <x-menu-item title="Tauschbörse" link="{{ route('romantausch.index') }}" />
            </x-menu-sub>

            <x-menu-sub title="Veranstaltungen" icon="o-calendar">
                <x-menu-item title="Fotos" link="{{ route('fotogalerie') }}" />
                <x-menu-item title="Meetings" link="{{ route('meetings') }}" />
                <x-menu-item title="Termine" link="{{ route('termine') }}" />
            </x-menu-sub>

            <x-menu-sub title="Baxx" icon="o-bolt">
                <x-menu-item title="Challenges" link="{{ route('todos.index') }}" />
                <x-menu-item title="Belohnungen" link="{{ route('rewards.index') }}" />
            </x-menu-sub>

            <x-menu-sub title="Belohnungen" icon="o-gift">
                <x-menu-item title="Maddraxiversum" link="{{ route('maddraxiversum.index') }}" />
                <x-menu-item title="Downloads" link="{{ route('downloads') }}" />
                <x-menu-item title="Kompendium" link="{{ route('kompendium.index') }}" />
                <x-menu-item title="Statistik" link="{{ route('statistik.index') }}" />
            </x-menu-sub>

            @if(Auth::user()->teams()->where('personal_team', false)->exists() || Auth::user()->hasVorstandRole())
                <x-menu-sub title="AG" icon="o-rectangle-group">
                    @if(Auth::user()->hasVorstandRole() || Auth::user()->isMemberOfTeam('AG Fanhörbücher'))
                        <x-menu-item title="EARDRAX Dashboard" link="{{ route('hoerbuecher.index') }}" />
                    @endif
                    @if(Auth::user()->isMemberOfTeam('AG Maddraxikon'))
                        <x-menu-item title="Kompendium" link="{{ route('kompendium.index') }}" />
                    @endif
                    @if(Auth::user()->ownedTeams()->where('personal_team', false)->exists())
                        <x-menu-item title="AG verwalten" link="{{ route('ag.index') }}" />
                    @endif
                </x-menu-sub>
            @endif

            @if(Auth::user()->hasAnyRole(\App\Enums\Role::Admin, \App\Enums\Role::Vorstand, \App\Enums\Role::Kassenwart))
                <x-menu-sub title="Vorstand" icon="o-shield-check">
                    <x-menu-item title="Statistik" link="{{ route('admin.statistiken.index') }}" />
                    <x-menu-item title="Anmeldungen FT" link="{{ route('admin.fantreffen.2026') }}" />
                    <x-menu-item title="Fanfiction" link="{{ route('admin.fanfiction.index') }}" />
                    @can('manage', \App\Models\Poll::class)
                        <x-menu-item title="Umfrage verwalten" link="{{ route('admin.umfragen.index') }}" />
                    @endcan
                </x-menu-sub>
            @endif

            @if(Auth::user()->hasRole(\App\Enums\Role::Admin))
                <x-menu-sub title="Admin" icon="o-cog-6-tooth">
                    <x-menu-item title="Newsletter versenden" link="{{ route('newsletter.create') }}" />
                    <x-menu-item title="Kurznachrichten" link="{{ route('admin.messages.index') }}" />
                    <x-menu-item title="Charakter-Editor" link="{{ route('rpg.char-editor') }}" />
                    <x-menu-item title="Arbeitsgruppen" link="{{ route('arbeitsgruppen.index') }}" />
                </x-menu-sub>
            @endif

            <x-menu-separator />

            <x-menu-item title="Profil" link="{{ route('profile.show') }}" icon="o-user" />
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-menu-item title="Ausloggen" icon="o-arrow-right-on-rectangle" onclick="event.preventDefault(); this.closest('form').submit();" />
            </form>
        @endauth

        @guest
            <x-menu-item title="Fantreffen 2026" link="{{ route('fantreffen.2026') }}" icon="o-calendar-days" />
            @if(($showActivePollForGuest ?? false) && ($activePollMenuLabel ?? null))
                <x-menu-item title="{{ $activePollMenuLabel }}" link="{{ route('umfrage.aktuell') }}" icon="o-chart-bar" />
            @endif
            <x-menu-item title="Chronik" link="{{ route('chronik') }}" />
            <x-menu-item title="Ehrenmitglieder" link="{{ route('ehrenmitglieder') }}" />
            <x-menu-item title="Termine" link="{{ route('termine') }}" />
            <x-menu-item title="Arbeitsgruppen" link="{{ route('arbeitsgruppen') }}" />
            <x-menu-item title="Satzung" link="{{ route('satzung') }}" />
            <x-menu-item title="Mitglied werden" link="{{ route('mitglied.werden') }}" />
            <x-menu-item title="Spenden" link="{{ route('spenden') }}" />
            <x-menu-item title="Changelog" link="{{ route('changelog') }}" />
            <x-menu-separator />
            <x-menu-item title="Login" link="{{ route('login') }}" icon="o-arrow-right-on-rectangle" />
        @endguest
    </x-menu>
</div>
