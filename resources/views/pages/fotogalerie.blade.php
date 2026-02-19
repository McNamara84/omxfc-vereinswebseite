<x-app-layout title="Fotogalerie – Offizieller MADDRAX Fanclub e. V." description="Bilder von Veranstaltungen und Treffen des Fanclubs.">
    <x-member-page class="max-w-6xl">
        <x-header title="Fotogalerie">
            <x-slot:subtitle>
                Hier findest du Fotos von unseren Veranstaltungen aus den letzten Jahren.
            </x-slot:subtitle>
        </x-header>

        <x-card shadow>

        <!-- Jahr-Tabs -->
        <div class="mb-8">
            <div class="border-b border-base-content/10">
                <nav class="flex -mb-px space-x-8" aria-label="Tabs">
                    @foreach($years as $year)
                        <button
                            class="jahr-tab py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap
                            {{ $year === $activeYear ? 'border-primary text-primary' : 'border-transparent text-base-content hover:text-base-content hover:border-base-content/30' }}"
                            data-year="{{ $year }}"
                        >
                            Fotos {{ $year }}
                        </button>
                    @endforeach
                </nav>
            </div>
        </div>

        <!-- Fotogalerien - pro Jahr eine -->
        @foreach($years as $year)
            <div id="gallery-{{ $year }}" class="gallery-container {{ $year === $activeYear ? 'block' : 'hidden' }}">
                <div class="relative">
                    <!-- Navigation-Buttons -->
                    <button class="prev-button absolute left-0 top-1/2 transform -translate-y-1/2 bg-black bg-opacity-50 text-white p-2 rounded-r-lg z-10 hover:bg-opacity-70">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    
                    <!-- Hauptbild-Container -->
                    <div class="main-image-container h-64 sm:h-80 md:h-96 lg:h-[500px] flex items-center justify-center mb-4 bg-base-200 rounded-lg overflow-hidden">
                        @if(isset($photos[$year]) && count($photos[$year]) > 0)
                            <img src="{{ $photos[$year][0] }}" alt="Foto {{ $year }}" class="main-image object-contain max-h-full max-w-full">
                        @else
                            <div class="text-base-content">Keine Fotos für {{ $year }} verfügbar</div>
                        @endif
                    </div>
                    
                    <button class="next-button absolute right-0 top-1/2 transform -translate-y-1/2 bg-black bg-opacity-50 text-white p-2 rounded-l-lg z-10 hover:bg-opacity-70">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>

                <!-- Thumbnail-Navigation -->
                <div class="thumbnails-container flex overflow-x-auto gap-2 pb-2 mt-4">
                    @if(isset($photos[$year]))
                        @foreach($photos[$year] as $index => $photoUrl)
                            <img @if($index > 0) loading="lazy" @endif
                                src="{{ $photoUrl }}"
                                alt="Thumbnail {{ $index + 1 }}"
                                class="thumbnail h-20 w-auto object-cover cursor-pointer rounded {{ $index === 0 ? 'ring-2 ring-primary' : '' }}"
                                data-index="{{ $index }}"
                            >
                        @endforeach
                    @endif
                </div>
            </div>
        @endforeach
        </x-card>
    </x-member-page>
</x-app-layout>