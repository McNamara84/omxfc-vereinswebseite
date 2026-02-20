<x-app-layout title="Startseite – Offizieller MADDRAX Fanclub e. V." :description="$homeDescription">
    <x-public-page>
        <x-header title="Willkommen beim Offiziellen MADDRAX Fanclub e. V.!" class="mb-8 text-center" useH1 />

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Fotogalerie --}}
            <div class="md:col-span-2 bg-base-100 rounded-lg shadow-md overflow-hidden">
                <div id="gallery" class="relative w-full h-48 sm:h-64 md:h-72">
                    @foreach($galleryImages as $image)
                        <picture>
                            <source type="image/avif" srcset="{{ asset($image . '.avif') }}" />
                            <source type="image/webp" srcset="{{ asset($image . '.webp') }}" />
                            <img loading="lazy" src="{{ asset($image . '.webp') }}" alt="Foto von einem Treffen des Vereins mit einem Teil der Mitglieder"
                                class="absolute inset-0 w-full h-full object-cover opacity-0 transition-opacity duration-1000">
                        </picture>
                    @endforeach
                </div>
            </div>

            {{-- Fantreffen 2026 Banner --}}
            <div class="md:col-span-2 bg-primary text-primary-content rounded-lg shadow-lg p-6">
                <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-bold mb-2">🎉 Maddrax-Fantreffen 2026 in Köln</h2>
                        <p class="text-primary-content/90">
                            <strong>Samstag, 9. Mai 2026</strong> – Signierstunde mit Autoren, Verleihung der Goldenen Taratze & mehr!
                        </p>
                    </div>
                    <a href="{{ route('fantreffen.2026') }}" 
                       class="btn btn-secondary whitespace-nowrap">
                        Jetzt anmelden →
                    </a>
                </div>
            </div>

            {{-- Wer wir sind --}}
            <div class="bg-base-100 rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-semibold text-primary mb-4">Wer wir sind</h2>
                <p class="text-base-content/70">{{ $whoWeAre }}</p>
            </div>

            {{-- Was wir machen --}}
            <div class="bg-base-100 rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-semibold text-primary mb-4">Was wir machen</h2>
                <p class="text-base-content/70">{{ $whatWeDo }}</p>
            </div>

            {{-- Aktuelle Projekte --}}
            <div class="md:col-span-2 bg-base-100 rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-semibold text-primary mb-4">Aktuelle Projekte</h2>
                <ul class="list-disc ml-5 text-base-content/70 space-y-2">
                    @foreach($currentProjects as $project)
                    <li><strong>{{ $project['title'] }}</strong>: {{ $project['description'] }}</li>
                    @endForeach
                </ul>
            </div>

            {{-- Vorteile einer Mitgliedschaft --}}
            <div class="bg-base-100 rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-semibold text-primary mb-4">Vorteile einer Mitgliedschaft
                </h2>
                <ul class="list-disc ml-5 text-base-content/70">
                    @foreach($membershipBenefits as $benefit)
                        <li>{{ $benefit }}</li>
                    @endforeach
                </ul>
            </div>

            {{-- Letzte Rezensionen --}}
            <div class="bg-base-100 rounded-lg shadow-md p-6" id="latest-reviews-card">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="text-2xl font-semibold text-primary">Letzte Rezensionen</h2>
                    @auth
                        <a class="text-sm font-semibold link link-primary" href="{{ route('reviews.index') }}">
                            Alle ansehen
                        </a>
                    @else
                        <a class="text-sm font-semibold link link-primary" href="{{ route('mitglied.werden') }}">
                            Alle ansehen
                        </a>
                    @endauth
                </div>
                <p class="mt-1 text-sm text-base-content/60">Die neuesten Eindrücke aus unserer Community.</p>

                <div id="latest-reviews-loading" class="mt-4 space-y-3" role="status" aria-live="polite" aria-busy="true">
                    <div class="flex items-center gap-2 text-base-content/60">
                        <span class="inline-block h-2 w-2 rounded-full bg-primary animate-pulse"></span>
                        <span>Lädt Community-Highlights …</span>
                    </div>
                    <div class="space-y-2" aria-hidden="true">
                        @for($i = 0; $i < 3; $i++)
                            <div class="h-3 rounded bg-base-200 animate-pulse"></div>
                        @endfor
                    </div>
                </div>

                <p id="latest-reviews-empty" class="mt-4 text-sm text-base-content/60 hidden" role="status" aria-live="polite">
                    Derzeit liegen keine Rezensionen vor. Schau später noch einmal vorbei.
                </p>

                <ul id="latest-reviews-list" class="mt-4 divide-y divide-base-content/10 hidden" aria-label="Neueste Rezensionen" data-api-url="{{ route('api.reviews.latest') }}">
                </ul>
            </div>

            {{-- Kennzahlen --}}
            <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-base-100 rounded-lg shadow-md p-6 flex flex-col items-center" aria-labelledby="stat-members-heading" aria-describedby="stat-members-description">
                    <h3 id="stat-members-heading" class="text-lg font-semibold text-primary">Aktive Mitglieder</h3>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-4xl font-bold text-primary">{{ $memberCount }}</span>
                        <span class="text-base-content/70">aktive Mitglieder</span>
                    </div>
                    <p id="stat-members-description" class="mt-3 text-sm text-base-content/60 text-center">Gemeinschaft, die sich regelmäßig austauscht und Projekte voranbringt.</p>
                </div>
                <div class="bg-base-100 rounded-lg shadow-md p-6 flex flex-col items-center" aria-labelledby="stat-reviews-heading" aria-describedby="stat-reviews-description">
                    <h3 id="stat-reviews-heading" class="text-lg font-semibold text-primary">Rezensionen</h3>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-4xl font-bold text-primary">{{ $reviewCount }}</span>
                        <span class="text-base-content/70">Rezensionen</span>
                    </div>
                    <p id="stat-reviews-description" class="mt-3 text-sm text-base-content/60 text-center">Lesetipps und Eindrücke zu den Romanen unserer Lieblingsserie.</p>
                </div>
            </div>
        </div>
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
