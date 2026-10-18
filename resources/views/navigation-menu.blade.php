@if (($variant ?? 'public-navbar') === 'member-sidebar')
    @include('navigation.member-sidebar')
@elseif (($variant ?? 'public-navbar') === 'member-profile')
    @include('navigation.profile-menu')
@else
@php
    $featuredNavigation = $navigation['featured'] ?? [];
    $sectionNavigation = $navigation['sections'] ?? [];
    $directNavigation = $navigation['direct'] ?? [];
    $brandHref = $publicHome;
@endphp

<div x-data="{ mobileOpen: false }"
    x-on:keydown.escape="if (mobileOpen) { $event.stopPropagation(); mobileOpen = false; $refs.mobileToggle.focus() }"
    x-on:livewire:navigating.document="mobileOpen = false"
    x-on:navigation-search-opening.window="mobileOpen = false"
    x-on:resize.window="if (window.innerWidth >= 1280) mobileOpen = false">
    <nav aria-label="Hauptnavigation">
        <x-mary-nav sticky>
            <x-slot:brand>
                <div class="flex items-center gap-3">
                    <a href="{{ $brandHref }}" wire:navigate class="shrink-0 rounded-full bg-base-100/80 p-1 ring-1 ring-base-content/10 transition hover:ring-primary/30" data-testid="navigation-brand-link" aria-label="Öffentliche Startseite">
                        <x-application-mark class="block h-9 w-auto" />
                    </a>

                    <div class="hidden min-[1800px]:block leading-tight">
                        <p class="text-[0.65rem] font-semibold uppercase tracking-[0.24em] text-base-content/75">OMXFC Community</p>
                        <p class="text-sm font-semibold text-base-content">Fanclub, Projekte und Veranstaltungen</p>
                    </div>
                </div>

                {{-- Desktop-Menü --}}
                <div class="hidden xl:flex xl:flex-1 xl:items-center xl:justify-between xl:gap-4 xl:pl-6">
                    <div class="rounded-xl border border-base-content/10 bg-base-100/55 px-3 py-2 backdrop-blur" aria-label="Schnellzugriff" data-testid="nav-featured-links">
                        <p class="hidden px-2 pb-2 text-[0.62rem] font-semibold uppercase tracking-[0.24em] text-base-content/75 2xl:block">Schnellzugriff</p>
                        <x-ui.action-cluster>
                            @foreach($featuredNavigation as $item)
                                <a
                                    href="{{ $item['href'] }}"
                                    wire:navigate
                                    @if($item['aria_current']) aria-current="{{ $item['aria_current'] }}" @endif
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
                                        <x-mary-icon :name="$item['icon']" class="h-4 w-4" />
                                    @endif
                                    <span>{{ $item['title'] }}</span>
                                </a>
                            @endforeach
                        </x-ui.action-cluster>
                    </div>

                    <div class="rounded-xl border border-base-content/10 bg-base-100/55 px-3 py-2 backdrop-blur" aria-label="Bereiche" data-testid="nav-sections">
                        <p class="hidden px-2 pb-2 text-[0.62rem] font-semibold uppercase tracking-[0.24em] text-base-content/75 2xl:block">Bereiche</p>
                        <x-ui.action-cluster>
                            @foreach($directNavigation as $item)
                                <a href="{{ $item['href'] }}" wire:navigate @if($item['aria_current']) aria-current="{{ $item['aria_current'] }}" @endif class="btn btn-ghost btn-sm rounded-full">{{ $item['title'] }}</a>
                            @endforeach
                            @foreach($sectionNavigation as $section)
                                <x-mary-dropdown :right="$loop->last" class="shrink-0">
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
                                                @if($item['aria_current']) aria-current="{{ $item['aria_current'] }}" @endif
                                                data-tour-device="desktop"
                                                @if($item['tour_key'] ?? null)
                                                    data-tour-key="{{ $item['tour_key'] }}"
                                                @endif
                                                @class(['my-0.5 flex w-full items-center gap-3 rounded-xl px-4 py-2 text-sm leading-5 text-base-content transition hover:bg-base-200/80 whitespace-nowrap', 'bg-primary/12 font-semibold' => $item['active']])
                                            >
                                                @if($item['icon'] ?? null)
                                                    <x-mary-icon :name="$item['icon']" class="h-4 w-4 shrink-0" />
                                                @endif
                                                <span class="whitespace-nowrap">{{ $item['title'] }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </x-mary-dropdown>
                            @endforeach
                        </x-ui.action-cluster>
                    </div>
                </div>
            </x-slot:brand>

            <x-slot:actions class="gap-1! sm:gap-2!">
                <x-ui.search-trigger />
                @if($canAccessMembers)
                    <a href="{{ route('dashboard') }}" wire:navigate class="btn btn-ghost min-h-11 min-w-11 px-2" aria-label="Zum Mitgliederbereich" data-testid="member-site-switch">
                        <x-mary-icon name="o-user-group" class="h-5 w-5" aria-hidden="true" />
                        <span class="hidden xl:inline">Mitgliederbereich</span>
                    </a>
                @endif
                <x-ui.theme-trigger />

                @auth
                    <livewire:navigation-menu variant="member-profile" :key="'public-profile-menu'" />
                @endauth
                @guest
                    <x-mary-button label="Login" :link="route('login')" wire:navigate class="btn-ghost btn-sm hidden xl:inline-flex" />
                @endguest

                {{-- Hamburger (Mobile) --}}
                <div class="flex items-center xl:hidden">
                    <button
                        type="button"
                        @click="mobileOpen = !mobileOpen"
                        aria-expanded="false"
                        :aria-expanded="mobileOpen"
                        x-bind:data-tour-open="mobileOpen ? 'true' : 'false'"
                        data-tour-device="mobile"
                        data-tour-key="mobile-menu-toggle"
                        aria-controls="mobile-navigation"
                        x-ref="mobileToggle"
                        class="btn btn-ghost min-h-11 min-w-11 px-2"
                    >
                        <x-mary-icon x-show="!mobileOpen" name="o-bars-3" class="w-6 h-6" aria-hidden="true" />
                        <x-mary-icon x-show="mobileOpen" x-cloak name="o-x-mark" class="w-6 h-6" aria-hidden="true" />
                        <span class="hidden text-sm sm:inline" aria-hidden="true" x-text="mobileOpen ? 'Schließen' : 'Menü'">Menü</span>
                        <span class="sr-only" x-text="mobileOpen ? 'Menü schließen' : 'Menü öffnen'">Menü öffnen</span>
                    </button>
                </div>
            </x-slot:actions>
        </x-mary-nav>

        {{-- Mobile-Menü --}}
        <div id="mobile-navigation"
            x-show="mobileOpen"
            x-cloak
            x-collapse
            class="xl:hidden border-b border-base-content/10 bg-base-100/95 backdrop-blur">
            <x-mary-menu class="p-2" data-testid="mobile-navigation-menu">
                <li class="menu-title px-4 pt-2 text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-base-content/75" data-testid="mobile-nav-featured-heading">Schnellzugriff</li>
                @foreach($featuredNavigation as $item)
                    <x-mary-menu-item :title="$item['title']" :link="$item['href']" wire:navigate :icon="$item['icon'] ?? null" :active="$item['active']" :aria-current="$item['aria_current']" data-tour-device="mobile" :data-tour-key="$item['tour_key'] ?? null" />
                @endforeach

                <li role="separator" aria-hidden="true">
                    <hr class="my-3 border-t-[length:var(--border)] border-base-content/10" />
                </li>
                <li class="menu-title px-4 pt-2 text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-base-content/75" data-testid="mobile-nav-sections-heading">Bereiche</li>
                @foreach($directNavigation as $item)
                    <x-mary-menu-item :title="$item['title']" :link="$item['href']" :icon="$item['icon']" :active="$item['active']" :aria-current="$item['aria_current']" />
                @endforeach
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
                                    <x-mary-icon :name="$section['icon']" class="inline-flex my-0.5 h-4 w-4" />
                                @endif
                                <span class="mary-hideable whitespace-nowrap truncate">{{ $section['title'] }}</span>
                            </summary>
                            <ul class="mary-hideable">
                        @foreach($section['items'] as $item)
                                <x-mary-menu-item :title="$item['title']" :link="$item['href']" wire:navigate :icon="$item['icon'] ?? null" :active="$item['active']" :aria-current="$item['aria_current']" data-tour-device="mobile" :data-tour-key="$item['tour_key'] ?? null" />
                        @endforeach
                            </ul>
                        </details>
                    </li>
                @endforeach

                <li role="separator" aria-hidden="true">
                    <hr class="my-3 border-t-[length:var(--border)] border-base-content/10" />
                </li>

                <li><button type="button" x-on:click="$dispatch('mary-search-open')" class="min-h-11">Seite oder Funktion suchen</button></li>
                @if($canAccessMembers)
                    <x-mary-menu-item title="Zum Mitgliederbereich" :link="route('dashboard')" icon="o-user-group" />
                @endif
                @guest
                    <x-mary-menu-item title="Login" :link="route('login')" wire:navigate icon="o-arrow-right-on-rectangle" />
                @endguest
            </x-mary-menu>
        </div>
    </nav>
</div>
@endif
