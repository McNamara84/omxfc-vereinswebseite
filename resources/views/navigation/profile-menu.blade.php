@auth
    <x-mary-dropdown right>
        <x-slot:trigger
            class="flex min-h-11 items-center gap-2 rounded-full px-1.5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
            data-testid="profile-menu-trigger"
            data-tour-key="profile-menu"
            aria-label="Profilmenü von {{ Auth::user()->name }} öffnen"
            aria-expanded="false"
            x-bind:aria-expanded="open.toString()"
            x-bind:data-tour-open="open ? 'true' : 'false'"
            x-on:keydown.escape.window="if (open) { open = false; $refs.button.focus() }"
        >
            <img class="h-8 w-8 rounded-full object-cover" src="{{ Auth::user()->profile_photo_url }}" alt="" />
            <span class="hidden max-w-36 truncate text-sm font-semibold text-base-content md:block">
                {{ Auth::user()->publicFirstName() ?: Auth::user()->nicknameOrName() }}
            </span>
            <x-mary-icon name="o-chevron-down" class="hidden h-4 w-4 md:block" aria-hidden="true" />
        </x-slot:trigger>

        <x-mary-menu-item title="Profil" :link="route('profile.show')" icon="o-user" data-tour-key="profile-settings" aria-label="Profil" />
        <x-mary-menu-separator />
        <x-mary-menu-item title="Impressum" :link="route('impressum')" icon="o-information-circle" aria-label="Impressum" />
        <x-mary-menu-item title="Datenschutz" :link="route('datenschutz')" icon="o-shield-check" aria-label="Datenschutz" />
        <x-mary-menu-item title="Changelog" :link="route('changelog')" icon="o-clock" aria-label="Changelog" />
        <x-mary-menu-separator />
        <li>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="my-0.5 flex w-full items-center gap-3 whitespace-nowrap rounded-xl px-4 py-2 text-sm leading-5 text-base-content transition hover:bg-base-200/80 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                    <x-mary-icon name="o-arrow-right-on-rectangle" class="h-4 w-4 shrink-0" />
                    <span>Ausloggen</span>
                </button>
            </form>
        </li>
    </x-mary-dropdown>
@else
    <span class="hidden" aria-hidden="true" data-testid="profile-menu-guest-placeholder"></span>
@endauth
