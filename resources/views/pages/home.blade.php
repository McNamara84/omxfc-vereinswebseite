<x-app-layout title="Startseite – Offizieller MADDRAX Fanclub e. V." :description="$homeDescription">
    <x-public-page class="space-y-8">
        <x-ui.page-header
            eyebrow="Offizieller MADDRAX Fanclub e. V."
            title="Willkommen beim Offiziellen MADDRAX Fanclub e. V.!"
            :description="$homeDescription"
        >
            <x-slot:actions>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="badge badge-primary badge-outline rounded-full px-3 py-3">{{ $memberCount }} aktive Mitglieder</span>
                    <span class="badge badge-outline rounded-full px-3 py-3">{{ $reviewCount }} Rezensionen</span>
                </div>

                <div class="flex flex-wrap gap-2">
                    @guest
                        <a href="{{ route('mitglied.werden') }}" wire:navigate class="btn btn-primary btn-sm rounded-full">Mitglied werden</a>
                    @endguest
                    <a href="{{ route('fantreffen.2026') }}" wire:navigate class="btn btn-ghost btn-sm rounded-full bg-base-100/70">Fantreffen 2026</a>
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        <section class="grid gap-8 xl:grid-cols-[minmax(0,1.55fr)_minmax(20rem,0.9fr)] xl:items-stretch">
            <div class="relative overflow-hidden rounded-[2rem] border border-base-content/10 bg-neutral text-neutral-content shadow-2xl shadow-base-content/10">
                <div id="gallery" class="relative h-full min-h-[22rem] w-full"
                    x-data="{
                        current: 0,
                        images: {{ count($galleryImages) }},
                        _interval: null,
                        init() {
                            this.$el.querySelectorAll('img')[0]?.classList.remove('opacity-0');
                            if (this.images <= 1) return;
                            this._interval = setInterval(() => {
                                this.$el.querySelectorAll('img')[this.current]?.classList.add('opacity-0');
                                this.current = (this.current + 1) % this.images;
                                this.$el.querySelectorAll('img')[this.current]?.classList.remove('opacity-0');
                            }, 4000);
                        },
                        destroy() {
                            if (this._interval) clearInterval(this._interval);
                        }
                    }">
                    @foreach($galleryImages as $image)
                        <picture>
                            <source type="image/avif" srcset="{{ asset($image . '.avif') }}" />
                            <source type="image/webp" srcset="{{ asset($image . '.webp') }}" />
                            <img loading="lazy" src="{{ asset($image . '.webp') }}" alt="Foto von einem Treffen des Vereins mit einem Teil der Mitglieder"
                                class="absolute inset-0 h-full w-full object-cover opacity-0 transition-opacity duration-1000">
                        </picture>
                    @endforeach

                    <div class="absolute inset-0 bg-linear-to-t from-neutral via-neutral/30 to-transparent"></div>
                    <div class="absolute inset-x-0 bottom-0 space-y-4 p-6 sm:p-8">
                        <p class="text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-neutral-content/60">Community im echten Leben</p>
                        <h2 class="font-display text-3xl font-semibold tracking-tight text-white sm:text-4xl">Fantreffen, Arbeitsgruppen und echte Begegnungen</h2>
                        <p class="max-w-2xl text-sm leading-relaxed text-neutral-content/80 sm:text-base">
                            Von der Chronik bis zum Fantreffen: Wir bauen keine lose Kommentarspalte, sondern eine aktive Fan-Community mit Projekten, Austausch und greifbaren Ergebnissen.
                        </p>
                    </div>
                </div>
            </div>

            <x-ui.panel title="Was dich hier erwartet" description="Die wichtigsten Einstiege für neue und langjährige Fans auf einen Blick.">
                <div class="grid gap-3">
                    <div class="rounded-[1.5rem] border border-base-content/10 bg-base-100/72 p-4">
                        <h3 class="font-semibold text-base-content">Community statt Karteileiche</h3>
                        <p class="mt-1 text-sm leading-relaxed text-base-content/72">Regelmäßiger Austausch, gemeinsame Events und Aufgaben mit echtem Vereinsleben.</p>
                    </div>
                    <div class="rounded-[1.5rem] border border-base-content/10 bg-base-100/72 p-4">
                        <h3 class="font-semibold text-base-content">Fanprojekte mit Substanz</h3>
                        <p class="mt-1 text-sm leading-relaxed text-base-content/72">Maddraxikon, EARDRAX, MAPDRAX und weitere Projekte werden gemeinsam weiterentwickelt.</p>
                    </div>
                    <div class="rounded-[1.5rem] border border-base-content/10 bg-base-100/72 p-4">
                        <h3 class="font-semibold text-base-content">Direkter Einstieg</h3>
                        <p class="mt-1 text-sm leading-relaxed text-base-content/72">Du kannst direkt Mitglied werden, Rezensionen entdecken oder dich fürs nächste Fantreffen anmelden.</p>
                    </div>
                </div>
            </x-ui.panel>
        </section>

        <section class="grid gap-8 lg:grid-cols-2">
            <x-ui.panel title="Wer wir sind" description="Eine vielfältige Fan-Community mit gemeinsamer Leidenschaft für das Maddraxiversum.">
                <p class="text-base leading-relaxed text-base-content/78">{{ $whoWeAre }}</p>
            </x-ui.panel>

            <x-ui.panel title="Was wir machen" description="Vom lockeren Austausch bis zu langfristigen Gemeinschaftsprojekten.">
                <p class="text-base leading-relaxed text-base-content/78">{{ $whatWeDo }}</p>
            </x-ui.panel>
        </section>

        <x-ui.panel title="Aktuelle Projekte" description="Diese Vorhaben prägen gerade den größten Teil unseres Vereinslebens.">
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach($currentProjects as $project)
                    <article class="rounded-[1.5rem] border border-base-content/10 bg-base-100/72 p-5">
                        <h3 class="font-display text-xl font-semibold tracking-tight text-base-content">{{ $project['title'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-base-content/72">{{ $project['description'] }}</p>
                    </article>
                @endforeach
            </div>
        </x-ui.panel>

        <section class="grid gap-8 lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
            <x-ui.panel title="Vorteile einer Mitgliedschaft" description="Warum sich der Schritt vom stillen Mitlesen zur aktiven Mitgliedschaft lohnt.">
                <ul class="grid gap-3">
                    @foreach($membershipBenefits as $benefit)
                        <li class="flex items-start gap-3 rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3 text-sm leading-relaxed text-base-content/78">
                            <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/12 text-primary">✓</span>
                            <span>{{ $benefit }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-ui.panel>

            <x-ui.panel id="latest-reviews-card" title="Letzte Rezensionen" description="Die neuesten Eindrücke, Lesetipps und Diskussionen aus unserer Community.">
                <x-slot:actions>
                    @auth
                        <a class="text-sm font-semibold link link-primary" href="{{ route('reviews.index') }}" wire:navigate>
                            Alle ansehen
                        </a>
                    @else
                        <a class="text-sm font-semibold link link-primary" href="{{ route('mitglied.werden') }}" wire:navigate>
                            Alle ansehen
                        </a>
                    @endauth
                </x-slot:actions>

                <livewire:home-reviews />
            </x-ui.panel>
        </section>

        <section class="grid gap-4 sm:grid-cols-2">
            <x-bento-card title="Aktive Mitglieder" sr-text="{{ $memberCount }} aktive Mitglieder" icon="o-user-group" description-id="stat-members-description">
                <x-slot:description>Gemeinschaft, die sich regelmäßig austauscht und Projekte voranbringt.</x-slot:description>
                <x-slot:value>{{ $memberCount }}</x-slot:value>
            </x-bento-card>

            <x-bento-card title="Rezensionen" sr-text="{{ $reviewCount }} Rezensionen" icon="o-book-open" description-id="stat-reviews-description">
                <x-slot:description>Lesetipps und Eindrücke zu den Romanen unserer Lieblingsserie.</x-slot:description>
                <x-slot:value>{{ $reviewCount }}</x-slot:value>
            </x-bento-card>
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
