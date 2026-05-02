@php
    $featuredNavigation = $navigation['featured'] ?? [];
    $sectionNavigation = $navigation['sections'] ?? [];
@endphp

<div>
    <nav aria-label="Hauptnavigation">
        <x-nav sticky>
            <x-slot:brand>
                <div class="flex items-center gap-3">
                    <a href="{{ route('home') }}" wire:navigate class="shrink-0 rounded-full bg-base-100/80 p-1 ring-1 ring-base-content/10 transition hover:ring-primary/30">
                        <x-application-mark class="block h-9 w-auto" />
                    </a>

                    <div class="hidden 2xl:block leading-tight">
                        <p class="text-[0.65rem] font-semibold uppercase tracking-[0.24em] text-base-content/50">OMXFC Community</p>
                        <p class="text-sm font-semibold text-base-content">Fanclub, Projekte und Veranstaltungen</p>
                    </div>
                </div>

                {{-- Desktop-Menü --}}
                <div class="hidden xl:flex xl:flex-1 xl:items-center xl:justify-between xl:gap-4 xl:pl-6">
                    <div class="rounded-[1.5rem] border border-base-content/10 bg-base-100/55 px-3 py-2 backdrop-blur" aria-label="Schnellzugriff" data-testid="nav-featured-links">
                        <p class="hidden px-2 pb-2 text-[0.62rem] font-semibold uppercase tracking-[0.24em] text-base-content/45 2xl:block">Schnellzugriff</p>
                        <x-ui.action-cluster>
                            @foreach($featuredNavigation as $item)
                                <a
                                    href="{{ $item['href'] }}"
                                    @class([
                                        'btn btn-sm rounded-full whitespace-nowrap border transition',
                                        'btn-primary border-primary text-primary-content shadow-sm' => $item['accent'] || $item['active'],
                                        'btn-ghost border-base-content/10 bg-base-100/70 hover:border-primary/30 hover:bg-base-100' => ! $item['accent'] && ! $item['active'],
                                    ])
                                >
                                    @if($item['icon'])
                                        <x-icon :name="$item['icon']" class="h-4 w-4" />
                                    @endif
                                    <span>{{ $item['title'] }}</span>
                                </a>
                            @endforeach
                        </x-ui.action-cluster>
                    </div>

                    <div class="rounded-[1.5rem] border border-base-content/10 bg-base-100/55 px-3 py-2 backdrop-blur" aria-label="Bereiche" data-testid="nav-sections">
                        <p class="hidden px-2 pb-2 text-[0.62rem] font-semibold uppercase tracking-[0.24em] text-base-content/45 2xl:block">Bereiche</p>
                        <x-ui.action-cluster>
                            @foreach($sectionNavigation as $section)
                                <x-dropdown :label="$section['title']" class="btn-sm rounded-full whitespace-nowrap {{ $section['active'] ? 'btn-primary btn-outline' : 'btn-ghost bg-base-100/60' }}">
                                    @foreach($section['items'] as $item)
                                        <x-menu-item :title="$item['title']" :link="$item['href']" wire:navigate :icon="$item['icon'] ?? null" />
                                    @endforeach
                                </x-dropdown>
                            @endforeach
                        </x-ui.action-cluster>
                    </div>
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
                            <x-menu-item title="Profil" link="{{ route('profile.show') }}" wire:navigate icon="o-user" />
                            <x-menu-separator />
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-menu-item title="Ausloggen" icon="o-arrow-right-on-rectangle" onclick="event.preventDefault(); this.closest('form').submit();" />
                            </form>
                        </x-dropdown>
                    @endauth

                    @guest
                        <x-button label="Login" link="{{ route('login') }}" wire:navigate class="btn-ghost btn-sm" />
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
            class="xl:hidden border-b border-base-content/10 bg-base-100/95 backdrop-blur">
            <x-menu class="p-2" data-testid="mobile-navigation-menu">
                <li class="menu-title px-4 pt-2 text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-base-content/45" data-testid="mobile-nav-featured-heading">Schnellzugriff</li>
                @foreach($featuredNavigation as $item)
                    <x-menu-item :title="$item['title']" :link="$item['href']" wire:navigate :icon="$item['icon'] ?? null" />
                @endforeach

                <x-menu-separator />
                <li class="menu-title px-4 pt-2 text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-base-content/45" data-testid="mobile-nav-sections-heading">Bereiche</li>
                @foreach($sectionNavigation as $section)
                    <x-menu-sub :title="$section['title']" :icon="$section['icon'] ?? 'o-ellipsis-horizontal-circle'">
                        @foreach($section['items'] as $item)
                            <x-menu-item :title="$item['title']" :link="$item['href']" wire:navigate :icon="$item['icon'] ?? null" />
                        @endforeach
                    </x-menu-sub>
                @endforeach

                <x-menu-separator />

                @auth
                    <x-menu-item title="Profil" :link="route('profile.show')" wire:navigate icon="o-user" />
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-menu-item title="Ausloggen" icon="o-arrow-right-on-rectangle" onclick="event.preventDefault(); this.closest('form').submit();" />
                    </form>
                @endauth

                @guest
                    <x-menu-item title="Login" :link="route('login')" wire:navigate icon="o-arrow-right-on-rectangle" />
                @endguest
            </x-menu>
        </div>
    </nav>
</div>
