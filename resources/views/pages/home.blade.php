<x-app-layout title="Startseite – Offizieller MADDRAX Fanclub e. V." description="Aktuelle Projekte, Chronik und Vorteile einer Mitgliedschaft im offiziellen MADDRAX Fanclub e. V.">
    <x-public-page>
        <h1 class="text-3xl font-bold text-[#8B0116] dark:text-[#ff4b63] mb-8 text-center">Willkommen beim Offiziellen MADDRAX Fanclub e. V.!</h1>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Fotogalerie --}}
            <div class="md:col-span-2 bg-white dark:bg-gray-700 rounded-lg shadow-md overflow-hidden">
                <div id="gallery" class="relative w-full h-48 sm:h-64 md:h-72">
                    @foreach($galleryImages as $image)
                        <img loading="lazy" src="{{ asset($image) }}" alt="Foto von einem Treffen des Vereins mit einem Teil der Mitglieder"
                            class="absolute inset-0 w-full h-full object-cover opacity-0 transition-opacity duration-1000">
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

            {{-- Anzahl Mitglieder --}}
            <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6 flex flex-col justify-center items-center">
                <h2 class="text-4xl font-bold text-[#8B0116] dark:text-[#ff4b63]">{{ $memberCount }}</h2>
                <span class="text-gray-700 dark:text-gray-300">aktive Mitglieder</span>
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
