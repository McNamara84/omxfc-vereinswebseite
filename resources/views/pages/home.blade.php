<x-app-layout title="Startseite – Offizieller MADDRAX Fanclub e. V." :description="$homeDescription">
    <x-public-page>
        <h1 class="text-3xl font-bold text-[#8B0116] dark:text-[#ff4b63] mb-8 text-center">Willkommen beim Offiziellen MADDRAX Fanclub e. V.!</h1>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Fotogalerie --}}
            <div class="md:col-span-2 bg-white dark:bg-gray-700 rounded-lg shadow-md overflow-hidden">
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
            <div class="md:col-span-2 bg-gradient-to-r from-[#8B0116] to-[#a01526] rounded-lg shadow-lg p-6 text-white">
                <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-bold mb-2">🎉 Maddrax-Fantreffen 2026 in Köln</h2>
                        <p class="text-white/90">
                            <strong>Samstag, 9. Mai 2026</strong> – Signierstunde mit Autoren, Verleihung der Goldenen Taratze & mehr!
                        </p>
                    </div>
                    <a href="{{ route('fantreffen.2026') }}" 
                       class="inline-block px-6 py-3 bg-white text-[#8B0116] font-bold rounded-lg hover:bg-gray-100 transition whitespace-nowrap">
                        Jetzt anmelden →
                    </a>
                </div>
            </div>

            {{-- Wer wir sind --}}
            <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-semibold text-[#8B0116] dark:text-[#ff4b63] mb-4">Wer wir sind</h2>
                <p class="text-gray-700 dark:text-gray-300">{{ $whoWeAre }}</p>
            </div>

            {{-- Was wir machen --}}
            <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-semibold text-[#8B0116] dark:text-[#ff4b63] mb-4">Was wir machen</h2>
                <p class="text-gray-700 dark:text-gray-300">{{ $whatWeDo }}</p>
            </div>

            {{-- Aktuelle Projekte --}}
            <div class="md:col-span-2 bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-semibold text-[#8B0116] dark:text-[#ff4b63] mb-4">Aktuelle Projekte</h2>
                <ul class="list-disc ml-5 text-gray-700 dark:text-gray-300 space-y-2">
                    @foreach($currentProjects as $project)
                    <li><strong>{{ $project['title'] }}</strong>: {{ $project['description'] }}</li>
                    @endForeach
                </ul>
            </div>

            {{-- Vorteile einer Mitgliedschaft --}}
            <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-semibold text-[#8B0116] dark:text-[#ff4b63] mb-4">Vorteile einer Mitgliedschaft
                </h2>
                <ul class="list-disc ml-5 text-gray-700 dark:text-gray-300">
                    @foreach($membershipBenefits as $benefit)
                        <li>{{ $benefit }}</li>
                    @endforeach
                </ul>
            </div>

            {{-- Letzte Rezensionen --}}
            <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6" id="latest-reviews-card">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="text-2xl font-semibold text-[#8B0116] dark:text-[#ff4b63]">Letzte Rezensionen</h2>
                    @auth
                        <a class="text-sm font-semibold text-[#8B0116] dark:text-[#ff4b63] hover:underline" href="{{ route('reviews.index') }}">
                            Alle ansehen
                        </a>
                    @else
                        <a class="text-sm font-semibold text-[#8B0116] dark:text-[#ff4b63] hover:underline" href="{{ route('mitglied.werden') }}">
                            Alle ansehen
                        </a>
                    @endauth
                </div>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Die neuesten Eindrücke aus unserer Community.</p>

                <div id="latest-reviews-loading" class="mt-4 space-y-3" role="status" aria-live="polite" aria-busy="true">
                    <div class="flex items-center gap-2 text-gray-600 dark:text-gray-300">
                        <span class="inline-block h-2 w-2 rounded-full bg-[#8B0116] animate-pulse"></span>
                        <span>Lädt Community-Highlights …</span>
                    </div>
                    <div class="space-y-2" aria-hidden="true">
                        @for($i = 0; $i < 3; $i++)
                            <div class="h-3 rounded bg-gray-200 dark:bg-gray-600 animate-pulse"></div>
                        @endfor
                    </div>
                </div>

                <p id="latest-reviews-empty" class="mt-4 text-sm text-gray-600 dark:text-gray-300 hidden" role="status" aria-live="polite">
                    Derzeit liegen keine Rezensionen vor. Schau später noch einmal vorbei.
                </p>

                <ul id="latest-reviews-list" class="mt-4 divide-y divide-gray-200 dark:divide-gray-600 hidden" aria-label="Neueste Rezensionen">
                </ul>
            </div>

            {{-- Kennzahlen --}}
            <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6 flex flex-col items-center" aria-labelledby="stat-members-heading" aria-describedby="stat-members-description">
                    <h3 id="stat-members-heading" class="text-lg font-semibold text-[#8B0116] dark:text-[#ff4b63]">Aktive Mitglieder</h3>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-4xl font-bold text-[#8B0116] dark:text-[#ff4b63]">{{ $memberCount }}</span>
                        <span class="text-gray-700 dark:text-gray-300">aktive Mitglieder</span>
                    </div>
                    <p id="stat-members-description" class="mt-3 text-sm text-gray-600 dark:text-gray-400 text-center">Gemeinschaft, die sich regelmäßig austauscht und Projekte voranbringt.</p>
                </div>
                <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6 flex flex-col items-center" aria-labelledby="stat-reviews-heading" aria-describedby="stat-reviews-description">
                    <h3 id="stat-reviews-heading" class="text-lg font-semibold text-[#8B0116] dark:text-[#ff4b63]">Rezensionen</h3>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-4xl font-bold text-[#8B0116] dark:text-[#ff4b63]">{{ $reviewCount }}</span>
                        <span class="text-gray-700 dark:text-gray-300">Rezensionen</span>
                    </div>
                    <p id="stat-reviews-description" class="mt-3 text-sm text-gray-600 dark:text-gray-400 text-center">Lesetipps und Eindrücke zu den Romanen unserer Lieblingsserie.</p>
                </div>
            </div>
        </div>
    </x-public-page>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const images = document.querySelectorAll('#gallery img');
            let current = 0;

            images[current].classList.remove('opacity-0');

            setInterval(() => {
                images[current].classList.add('opacity-0');
                current = (current + 1) % images.length;
                images[current].classList.remove('opacity-0');
            }, 4000);
        });
    </script>

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

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const list = document.getElementById('latest-reviews-list');
            const loading = document.getElementById('latest-reviews-loading');
            const empty = document.getElementById('latest-reviews-empty');

            const renderReview = (review) => {
                const item = document.createElement('li');
                item.className = 'py-4';

                const header = document.createElement('div');
                header.className = 'flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2';

                const badge = document.createElement('span');
                badge.className = 'inline-flex w-fit items-center gap-2 rounded-full bg-[#8B0116]/10 text-[#8B0116] dark:bg-[#ff4b63]/15 dark:text-[#ff4b63] px-3 py-1 text-xs font-semibold';
                badge.textContent = `Roman Nr. ${review.roman_number}`;
                badge.setAttribute('aria-label', `Roman Nummer ${review.roman_number}`);

                const romanTitle = document.createElement('p');
                romanTitle.className = 'text-sm text-gray-700 dark:text-gray-200 font-medium';
                romanTitle.textContent = review.roman_title;

                header.appendChild(badge);
                header.appendChild(romanTitle);

                const reviewTitle = document.createElement('h3');
                reviewTitle.className = 'mt-3 text-base font-semibold text-gray-900 dark:text-white flex flex-wrap items-center gap-1';

                const titleText = document.createElement('span');
                titleText.textContent = review.review_title;

                const reviewDate = document.createElement('time');
                reviewDate.className = 'text-sm font-normal text-gray-700 dark:text-gray-300';
                reviewDate.dateTime = review.reviewed_at;

                const parsedDate = new Date(review.reviewed_at);
                const formattedDate = Number.isNaN(parsedDate.getTime())
                    ? 'Unbekanntes Datum'
                    : new Intl.DateTimeFormat('de-DE', { dateStyle: 'medium' }).format(parsedDate);

                reviewDate.textContent = formattedDate;
                reviewDate.setAttribute('aria-label', `Rezension veröffentlicht am ${formattedDate}`);

                reviewTitle.appendChild(titleText);
                reviewTitle.appendChild(document.createTextNode(' vom '));
                reviewTitle.appendChild(reviewDate);

                const excerpt = document.createElement('p');
                excerpt.className = 'mt-2 text-sm text-gray-600 dark:text-gray-300 leading-relaxed';
                excerpt.textContent = review.excerpt;

                item.appendChild(header);
                item.appendChild(reviewTitle);
                item.appendChild(excerpt);

                return item;
            };

            fetch('{{ route('api.reviews.latest') }}', {
                headers: {
                    'Accept': 'application/json',
                },
            })
                .then((response) => response.ok ? response.json() : Promise.reject(response))
                .then((data) => {
                    loading.setAttribute('aria-busy', 'false');
                    loading.classList.add('hidden');
                    if (!Array.isArray(data) || data.length === 0) {
                        empty.classList.remove('hidden');
                        list.classList.add('hidden');
                        return;
                    }

                    list.innerHTML = '';
                    data.forEach((review) => list.appendChild(renderReview(review)));
                    list.classList.remove('hidden');
                })
                .catch(() => {
                    loading.setAttribute('aria-busy', 'false');
                    loading.classList.add('hidden');

                    const errorMessage = document.createElement('div');
                    errorMessage.className = 'mt-4 flex items-center gap-2 text-sm text-red-700 dark:text-red-300';
                    errorMessage.setAttribute('role', 'status');
                    errorMessage.setAttribute('aria-live', 'polite');

                    const dot = document.createElement('span');
                    dot.className = 'inline-block h-2 w-2 rounded-full bg-red-600';

                    const text = document.createElement('span');
                    text.textContent = 'Rezensionen konnten nicht geladen werden.';

                    errorMessage.appendChild(dot);
                    errorMessage.appendChild(text);

                    loading.after(errorMessage);
                });
        });
    </script>
</x-app-layout>
