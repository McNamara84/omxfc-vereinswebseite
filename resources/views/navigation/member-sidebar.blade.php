@php
    $sidebarUser = auth()->user();
    $sidebarUserSummary = $sidebarUser ? [
        'name' => $sidebarUser->nicknameOrName(),
        'role' => $sidebarUser->mitgliederTeamRole()?->value ?? 'Mitgliederbereich',
        'avatar' => $sidebarUser->profile_photo_url,
    ] : null;
    $featuredNavigation = $navigation['featured'] ?? [];
    $sectionNavigation = $navigation['sections'] ?? [];
    $dashboardNavigation = collect($featuredNavigation)->first(
        fn (array $item): bool => $item['href'] === route('dashboard')
    );
    $currentNavigation = collect($featuredNavigation)->reject(
        fn (array $item): bool => $item['href'] === route('dashboard')
    );
    $managementStarted = false;
@endphp

<div
    class="flex min-h-full flex-col"
    data-testid="member-sidebar-navigation"
    x-trap.noscroll="memberDrawerOpen && window.innerWidth < 1024"
>
    @if ($sidebarUserSummary)
        <a
            href="{{ route('profile.show') }}"
            wire:navigate
            aria-label="Profil von {{ $sidebarUserSummary['name'] }} öffnen"
            title="Profil von {{ $sidebarUserSummary['name'] }} öffnen"
            class="mx-2 mt-2 block rounded-2xl bg-base-200/65 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
        >
            <x-mary-list-item
                :item="$sidebarUserSummary"
                value="name"
                sub-value="role"
                no-separator
                no-hover
            >
                <x-slot:avatar>
                    <img
                        src="{{ $sidebarUserSummary['avatar'] }}"
                        alt=""
                        class="h-11 w-11 rounded-full object-cover"
                    />
                </x-slot:avatar>
            </x-mary-list-item>
        </a>
    @endif

    <x-mary-menu class="flex-1 px-2 pb-4 pt-2" active-bg-color="bg-primary/12 text-primary font-semibold">
        @if ($dashboardNavigation)
            <x-mary-menu-item
                :title="$dashboardNavigation['title']"
                :link="$dashboardNavigation['href']"
                :icon="$dashboardNavigation['icon']"
                :active="$dashboardNavigation['active']"
                :aria-current="$dashboardNavigation['active'] ? 'page' : null"
                :aria-label="$dashboardNavigation['title']"
                :title="$dashboardNavigation['title']"
                data-tour-key="dashboard"
            />
        @endif

        @if ($currentNavigation->isNotEmpty())
            <x-mary-menu-separator />
            <li class="menu-title mary-hideable px-4 pb-1 pt-2 text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-base-content/50">
                Aktuell
            </li>

            @foreach ($currentNavigation as $item)
                <x-mary-menu-item
                    :title="$item['title']"
                    :link="$item['href']"
                    :icon="$item['icon']"
                    :active="$item['active']"
                    :aria-current="$item['active'] ? 'page' : null"
                    :aria-label="$item['title']"
                    :title="$item['title']"
                    :badge="$item['accent'] ? 'Aktuell' : null"
                    badge-classes="badge-soft badge-primary"
                    :data-tour-key="$item['tour_key']"
                />
            @endforeach
        @endif

        <x-mary-menu-separator />
        <li class="menu-title mary-hideable px-4 pb-1 pt-2 text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-base-content/50">
            Bereiche
        </li>

        @foreach ($sectionNavigation as $section)
            @if (! $managementStarted && in_array($section['title'], ['Vorstand', 'Admin'], true))
                @php($managementStarted = true)
                <x-mary-menu-separator />
                <li class="menu-title mary-hideable px-4 pb-1 pt-2 text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-base-content/50">
                    Verwaltung
                </li>
            @endif

            <x-mary-menu-sub
                :title="$section['title']"
                :icon="$section['icon']"
                :open="$section['active']"
                :data-tour-key="$section['tour_key']"
                x-bind:data-tour-open="show ? 'true' : 'false'"
                x-on:click.self="$el.querySelector(':scope > details > summary')?.click()"
            >
                @foreach ($section['items'] as $item)
                    <x-mary-menu-item
                        :title="$item['title']"
                        :link="$item['href']"
                        :icon="$item['icon']"
                        :active="$item['active']"
                        :aria-current="$item['active'] ? 'page' : null"
                        :aria-label="$item['title']"
                        :title="$item['title']"
                        :data-tour-key="$item['tour_key']"
                    />
                @endforeach
            </x-mary-menu-sub>
        @endforeach
    </x-mary-menu>

    <div class="mary-hideable border-t border-base-content/10 px-5 py-4 text-xs text-base-content/55">
        <div class="flex flex-wrap gap-x-3 gap-y-1">
            <a href="{{ route('impressum') }}" wire:navigate class="link link-hover">Impressum</a>
            <a href="{{ route('datenschutz') }}" wire:navigate class="link link-hover">Datenschutz</a>
            <a href="{{ route('changelog') }}" wire:navigate class="link link-hover">Changelog</a>
        </div>
        <p class="mt-2">OMXFC · Version {{ $appVersion }}</p>
    </div>
</div>
