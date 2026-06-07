@php
    $featuredNavigation = $navigation['featured'] ?? [];
    $sectionNavigation = $navigation['sections'] ?? [];
@endphp

<div x-data="{ mobileOpen: false }">
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
                                    wire:navigate
                                    data-tour-device="desktop"
                                    @if($item['tour_key'] ?? null)
                                        data-tour-key="{{ $item['tour_key'] }}"
                                    @endif
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
                                <x-dropdown as="menu" :right="$loop->last" class="shrink-0">
                                    <x-slot:trigger>
                                        <div
                                            class="btn btn-sm rounded-full whitespace-nowrap {{ $section['active'] ? 'btn-primary btn-outline' : 'btn-ghost bg-base-100/60' }}"
                                            data-tour-device="desktop"
                                            @if($section['tour_key'] ?? null)
                                                data-tour-key="{{ $section['tour_key'] }}"
                                            @endif
                                            x-bind:data-tour-open="open ? 'true' : 'false'"
                                        >
                                            <span>{{ $section['title'] }}</span>
                                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    </x-slot:trigger>
                                    @foreach($section['items'] as $item)
                                        <li class="w-fit min-w-[14rem] max-w-[min(24rem,calc(100vw-2rem))]" data-testid="desktop-nav-dropdown-item">
                                            <a
                                                href="{{ $item['href'] }}"
                                                wire:navigate
                                                data-tour-device="desktop"
                                                @if($item['tour_key'] ?? null)
                                                    data-tour-key="{{ $item['tour_key'] }}"
                                                @endif
                                                class="my-0.5 flex w-full items-center gap-3 rounded-xl px-4 py-2 text-sm leading-5 text-base-content transition hover:bg-base-200/80 whitespace-nowrap"
                                            >
                                                @if($item['icon'] ?? null)
                                                    <x-icon :name="$item['icon']" class="h-4 w-4 shrink-0" />
                                                @endif
                                                <span class="whitespace-nowrap">{{ $item['title'] }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </x-dropdown>
                            @endforeach
                        </x-ui.action-cluster>
                    </div>
                </div>
            </x-slot:brand>

            <x-slot:actions>
                {{-- Theme-Toggle --}}
                <button
                    type="button"
                    data-testid="theme-toggle"
                    data-theme-toggle
                    aria-label="Dark Mode umschalten"
                    class="btn btn-ghost btn-sm btn-circle"
                >
                    <x-icon name="o-sun" class="h-5 w-5 dark:hidden" aria-hidden="true" />
                    <x-icon name="o-moon" class="hidden h-5 w-5 dark:inline-flex" aria-hidden="true" />
                </button>

                {{-- Profil-Dropdown / Login (Desktop) --}}
                <div class="hidden xl:flex xl:items-center">
                    @auth
                        <x-dropdown as="menu" right class="shrink-0">
                            <x-slot:trigger>
                                <div
                                    class="flex items-center"
                                    data-tour-device="desktop"
                                    data-tour-key="profile-menu"
                                    x-bind:data-tour-open="open ? 'true' : 'false'"
                                >
                                    <img class="h-8 w-8 rounded-full object-cover" src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->name }}" />
                                    <span class="sr-only">Profilmenü öffnen</span>
                                </div>
                            </x-slot:trigger>
                            <x-menu-item title="Profil" link="{{ route('profile.show') }}" wire:navigate icon="o-user" data-tour-device="desktop" data-tour-key="profile-settings" />
                            <x-menu-separator />
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="my-0.5 flex w-full items-center gap-3 rounded-xl px-4 py-2 text-sm leading-5 text-base-content transition hover:bg-base-200/80">
                                        <x-icon name="o-arrow-right-on-rectangle" class="h-4 w-4 shrink-0" />
                                        <span>Ausloggen</span>
                                    </button>
                                </form>
                            </li>
                        </x-dropdown>
                    @endauth

                    @guest
                        <x-button label="Login" link="{{ route('login') }}" wire:navigate class="btn-ghost btn-sm" />
                    @endguest
                </div>

                {{-- Hamburger (Mobile) --}}
                <div class="-mr-2 flex items-center xl:hidden">
                    <button
                        type="button"
                        @click="mobileOpen = !mobileOpen"
                        aria-expanded="false"
                        :aria-expanded="mobileOpen"
                        x-bind:data-tour-open="mobileOpen ? 'true' : 'false'"
                        data-tour-device="mobile"
                        data-tour-key="mobile-menu-toggle"
                        aria-controls="mobile-navigation"
                        class="btn btn-ghost btn-sm"
                    >
                        <x-icon x-show="!mobileOpen" name="o-bars-3" class="w-6 h-6" aria-hidden="true" />
                        <x-icon x-show="mobileOpen" x-cloak name="o-x-mark" class="w-6 h-6" aria-hidden="true" />
                        <span class="text-sm" aria-hidden="true" x-text="mobileOpen ? 'Schließen' : 'Menü'">Menü</span>
                        <span class="sr-only" x-text="mobileOpen ? 'Menü schließen' : 'Menü öffnen'">Menü öffnen</span>
                    </button>
                </div>
            </x-slot:actions>
        </x-nav>

        {{-- Mobile-Menü --}}
        <div id="mobile-navigation"
            x-show="mobileOpen"
            x-cloak
            x-collapse
            class="xl:hidden border-b border-base-content/10 bg-base-100/95 backdrop-blur">
            <x-menu class="p-2" data-testid="mobile-navigation-menu">
                <li class="menu-title px-4 pt-2 text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-base-content/45" data-testid="mobile-nav-featured-heading">Schnellzugriff</li>
                @foreach($featuredNavigation as $item)
                    <x-menu-item :title="$item['title']" :link="$item['href']" wire:navigate :icon="$item['icon'] ?? null" data-tour-device="mobile" :data-tour-key="$item['tour_key'] ?? null" />
                @endforeach

                <li role="separator" aria-hidden="true">
                    <hr class="my-3 border-t-[length:var(--border)] border-base-content/10" />
                </li>
                <li class="menu-title px-4 pt-2 text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-base-content/45" data-testid="mobile-nav-sections-heading">Bereiche</li>
                @foreach($sectionNavigation as $section)
                    <li x-data="{ open: {{ $section['active'] ? 'true' : 'false' }} }">
                        <details :open="open" @click.stop>
                            <summary
                                @click.prevent="open = !open"
                                class="hover:text-inherit px-4 py-1.5 my-0.5 text-inherit {{ $section['active'] ? 'bg-base-300' : '' }}"
                                data-tour-device="mobile"
                                @if($section['tour_key'] ?? null)
                                    data-tour-key="{{ $section['tour_key'] }}"
                                @endif
                                x-bind:data-tour-open="open ? 'true' : 'false'"
                                :aria-expanded="open"
                            >
                                @if($section['icon'] ?? null)
                                    <x-icon :name="$section['icon']" class="inline-flex my-0.5 h-4 w-4" />
                                @endif
                                <span class="mary-hideable whitespace-nowrap truncate">{{ $section['title'] }}</span>
                            </summary>
                            <ul class="mary-hideable">
                        @foreach($section['items'] as $item)
                                <x-menu-item :title="$item['title']" :link="$item['href']" wire:navigate :icon="$item['icon'] ?? null" data-tour-device="mobile" :data-tour-key="$item['tour_key'] ?? null" />
                        @endforeach
                            </ul>
                        </details>
                    </li>
                @endforeach

                <li role="separator" aria-hidden="true">
                    <hr class="my-3 border-t-[length:var(--border)] border-base-content/10" />
                </li>

                @auth
                    <x-menu-item title="Profil" :link="route('profile.show')" wire:navigate icon="o-user" data-tour-device="mobile" data-tour-key="profile-settings" />
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="my-0.5 flex w-full items-center gap-3 rounded-xl px-4 py-2 text-sm leading-5 text-base-content transition hover:bg-base-200/80 whitespace-nowrap">
                                <x-icon name="o-arrow-right-on-rectangle" class="h-5 w-5 shrink-0" />
                                <span>Ausloggen</span>
                            </button>
                        </form>
                    </li>
                @endauth

                @guest
                    <x-menu-item title="Login" :link="route('login')" wire:navigate icon="o-arrow-right-on-rectangle" />
                @endguest
            </x-menu>
        </div>
    </nav>
</div>
