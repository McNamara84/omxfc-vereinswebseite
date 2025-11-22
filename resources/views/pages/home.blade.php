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
                    <p id="stat-reviews-description" class="mt-3 text-sm text-gray-600 dark:text-gray-400 text-center">Lesetipps und Eindrücke zu unseren Romanen aus der Community.</p>
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
</x-app-layout>
