@php
    $hero = data_get($homeContent, 'hero', []);
    $gallery = data_get($hero, 'gallery', []);
    $storySections = data_get($homeContent, 'story', []);
    $projectsSection = data_get($homeContent, 'projects', []);
    $membershipSection = data_get($homeContent, 'membership', []);
    $reviewsSection = data_get($homeContent, 'reviews', []);
    $statValues = [
        'memberCount' => $memberCount,
        'reviewCount' => $reviewCount,
    ];
@endphp

<x-app-layout title="Startseite – Offizieller MADDRAX Fanclub e. V." :description="$homeDescription">
    <x-public-page class="space-y-6">
        <x-ui.page-header density="hero"
            :eyebrow="data_get($hero, 'eyebrow')"
            :title="data_get($hero, 'title')"
            :description="$homeDescription"
        >
            <x-slot:actions>
                <x-ui.action-cluster align="end">
                    <span class="badge badge-primary badge-outline rounded-full px-3 py-3">{{ $memberCount }} aktive Mitglieder</span>
                    <span class="badge badge-outline rounded-full px-3 py-3">{{ $reviewCount }} Rezensionen</span>
                </x-ui.action-cluster>

                <x-ui.action-cluster align="end">
                    @guest
                        <a href="{{ route('mitglied.werden') }}" wire:navigate class="btn btn-primary btn-sm rounded-full">Mitglied werden</a>
                    @endguest
                    <a href="{{ route('veranstaltungen.aktuell') }}" wire:navigate class="btn btn-ghost btn-sm rounded-full bg-base-100/70">Aktuelle Veranstaltung</a>
                </x-ui.action-cluster>
            </x-slot:actions>
        </x-ui.page-header>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.55fr)_minmax(20rem,0.9fr)] xl:items-stretch">
            <div class="relative overflow-hidden rounded-2xl border border-base-content/10 bg-neutral text-neutral-content shadow-2xl shadow-base-content/10">
                <div id="gallery" class="relative h-full min-h-[22rem] w-full"
                    x-data="{ current: 0, images: {{ count(data_get($gallery, 'images', [])) }}, move(step) { if (this.images) this.current = (this.current + step + this.images) % this.images } }"
                    role="region" aria-roledescription="Bildergalerie" aria-label="Treffen des Vereins" data-testid="home-gallery">
                    @foreach(data_get($gallery, 'images', []) as $image)
                        <picture x-show="current === {{ $loop->index }}" @if(! $loop->first) x-cloak @endif>
                            <source type="image/avif" srcset="{{ asset($image . '.avif') }}" />
                            <source type="image/webp" srcset="{{ asset($image . '.webp') }}" />
                            <img loading="{{ $loop->first ? 'eager' : 'lazy' }}" @if($loop->first) fetchpriority="high" @endif width="1200" height="800" src="{{ asset($image . '.webp') }}" alt="Foto von einem Treffen des Vereins mit einem Teil der Mitglieder"
                                class="absolute inset-0 h-full w-full object-cover">
                        </picture>
                    @endforeach

                    <div class="absolute inset-0 bg-linear-to-t from-neutral via-neutral/30 to-transparent"></div>
                    <div class="absolute inset-x-0 bottom-0 space-y-4 p-6 pb-20 sm:p-8 sm:pb-20">
                        <p class="text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-neutral-content/60">{{ data_get($gallery, 'eyebrow') }}</p>
                        <h2 class="font-display text-3xl font-semibold tracking-tight text-white sm:text-4xl">{{ data_get($gallery, 'title') }}</h2>
                        <p class="max-w-2xl text-sm leading-relaxed text-neutral-content/80 sm:text-base">
                            {{ data_get($gallery, 'description') }}
                        </p>
                    </div>
                    @if(count(data_get($gallery, 'images', [])) > 1)
                        <div class="absolute inset-x-0 bottom-0 flex items-center justify-between gap-3 bg-neutral px-6 py-3 text-neutral-content">
                            <button type="button" class="btn btn-sm min-h-11 bg-neutral text-neutral-content border-neutral-content/40" x-on:click="move(-1)" aria-label="Vorheriges Bild">←</button>
                            <span aria-live="polite" aria-atomic="true" x-text="'Bild ' + (current + 1) + ' von ' + images">Bild 1 von {{ count(data_get($gallery, 'images', [])) }}</span>
                            <button type="button" class="btn btn-sm min-h-11 bg-neutral text-neutral-content border-neutral-content/40" x-on:click="move(1)" aria-label="Nächstes Bild">→</button>
                        </div>
                    @endif
                </div>
            </div>

            <x-ui.panel title="Was dich hier erwartet" description="Die wichtigsten Einstiege für neue und langjährige Fans auf einen Blick.">
                <div class="grid gap-3">
                    @foreach(data_get($hero, 'highlights', []) as $highlight)
                        <div class="rounded-xl border border-base-content/10 bg-base-100/72 p-4">
                            <h3 class="font-semibold text-base-content">{{ $highlight['title'] }}</h3>
                            <p class="mt-1 text-sm leading-relaxed text-base-content/72">{{ $highlight['description'] }}</p>
                        </div>
                    @endforeach
                </div>
            </x-ui.panel>
        </section>

        <section class="grid gap-6 lg:grid-cols-2">
            @foreach($storySections as $section)
                <x-ui.panel :title="$section['title']" :description="$section['description']">
                    <p class="text-base leading-relaxed text-base-content/78">{{ $section['content'] }}</p>
                </x-ui.panel>
            @endforeach
        </section>

        <x-ui.panel :title="data_get($projectsSection, 'title')" :description="data_get($projectsSection, 'description')">
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach(data_get($projectsSection, 'items', []) as $project)
                    <article class="rounded-xl border border-base-content/10 bg-base-100/72 p-5">
                        <h3 class="font-display text-xl font-semibold tracking-tight text-base-content">{{ $project['title'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-base-content/72">{{ $project['description'] }}</p>
                    </article>
                @endforeach
            </div>
        </x-ui.panel>

        <section class="grid gap-6 lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
            <x-ui.panel :title="data_get($membershipSection, 'title')" :description="data_get($membershipSection, 'description')">
                <ul class="grid gap-3">
                    @foreach(data_get($membershipSection, 'items', []) as $benefit)
                        <li class="flex items-start gap-3 rounded-xl border border-base-content/10 bg-base-100/72 px-4 py-3 text-sm leading-relaxed text-base-content/78">
                            <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/12 text-primary">✓</span>
                            <span>{{ $benefit }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-ui.panel>

            <x-ui.panel id="latest-reviews-card" :title="data_get($reviewsSection, 'title')" :description="data_get($reviewsSection, 'description')">
                <x-slot:actions>
                    @auth
                        <a class="text-sm font-semibold link link-primary" href="{{ route('reviews.index') }}" wire:navigate>
                            {{ data_get($reviewsSection, 'member_cta') }}
                        </a>
                    @else
                        <a class="text-sm font-semibold link link-primary" href="{{ route('mitglied.werden') }}" wire:navigate>
                            {{ data_get($reviewsSection, 'guest_cta') }}
                        </a>
                    @endauth
                </x-slot:actions>

                <livewire:home-reviews />
            </x-ui.panel>
        </section>

        <section class="grid gap-4 sm:grid-cols-2">
            @foreach(data_get($homeContent, 'stats', []) as $stat)
                @php
                    $value = $statValues[$stat['value_key']] ?? null;
                @endphp
                <x-bento-card
                    :title="$stat['title']"
                    :sr-text="sprintf($stat['sr_text_template'], $value)"
                    :icon="$stat['icon']"
                    :description-id="$stat['description_id']"
                >
                    <x-slot:description>{{ $stat['description'] }}</x-slot:description>
                    <x-slot:value>{{ $value }}</x-slot:value>
                </x-bento-card>
            @endforeach
        </section>
    </x-public-page>

    <script type="application/ld+json">
        {!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>

    {{-- Organization-Schema für Google Rich Results --}}
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "Organization",
        "name": "Offizieller MADDRAX Fanclub e. V.",
        "alternateName": "OMXFC e. V.",
        "url": "{{ config('app.url') }}",
        "logo": "{{ asset('build/assets/omxfc-logo-Df-1StAj.png') }}",
        "description": "Der Offizielle MADDRAX Fanclub e. V. vernetzt Fans der postapokalyptischen Romanserie und informiert über Projekte, Termine und Mitgliedschaft.",
        "foundingDate": "2023",
        "address": {
            "@@type": "PostalAddress",
            "addressCountry": "DE"
        },
        "memberOf": {
            "@@type": "Thing",
            "name": "MADDRAX Fan-Community"
        }
    }
    </script>
</x-app-layout>
