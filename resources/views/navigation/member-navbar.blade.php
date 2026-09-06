<nav aria-label="Kopfleiste des Mitgliederbereichs">
    <x-mary-nav sticky full-width class="z-40 h-[65px] shadow-sm">
        <x-slot:brand class="min-w-0 gap-3">
            <x-mary-button
                type="button"
                icon="o-bars-3"
                class="btn-circle btn-ghost min-h-11 min-w-11 lg:hidden"
                data-testid="member-drawer-toggle"
                data-tour-key="mobile-menu-toggle"
                data-tour-device="mobile"
                aria-controls="member-drawer"
                aria-expanded="false"
                x-bind:aria-expanded="memberDrawerOpen.toString()"
                x-on:click="toggleMemberDrawer()"
                x-ref="memberDrawerToggle"
            >
                <span class="sr-only" x-text="memberDrawerOpen ? 'Hauptmenü schließen' : 'Hauptmenü öffnen'">Hauptmenü öffnen</span>
            </x-mary-button>

            <a
                href="{{ route('dashboard') }}"
                wire:navigate
                class="flex min-w-0 items-center gap-3 rounded-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                data-testid="member-navigation-brand-link"
            >
                <x-application-mark class="block h-9 w-auto shrink-0" />
                <span class="hidden truncate font-display text-sm font-semibold text-base-content sm:block">
                    OMXFC Mitgliederbereich
                </span>
            </a>
        </x-slot:brand>

        <x-slot:actions class="gap-2">
            <x-ui.theme-trigger />
            @auth
                <livewire:navigation-menu variant="member-profile" :key="'member-profile-menu'" />
            @endauth
        </x-slot:actions>
    </x-mary-nav>
</nav>
