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
                            {{ $year === $activeYear ? 'border-primary text-primary' : 'border-transparent text-base-content/50 hover:text-base-content/80 hover:border-base-content/30' }}"
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
                            <div class="text-base-content/50">Keine Fotos für {{ $year }} verfügbar</div>
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

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Jahr-Tab-Wechsel
        const jahresTabs = document.querySelectorAll('.jahr-tab');
        jahresTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const year = this.getAttribute('data-year');
                
                // Alle Tabs zurücksetzen
                jahresTabs.forEach(t => {
                    t.classList.remove('border-primary', 'text-primary');
                    t.classList.add('border-transparent', 'text-base-content/50');
                });
                
                // Aktiven Tab setzen
                this.classList.remove('border-transparent', 'text-base-content/50');
                this.classList.add('border-primary', 'text-primary');
                
                // Galerien anzeigen/verstecken
                document.querySelectorAll('.gallery-container').forEach(gallery => {
                    gallery.classList.add('hidden');
                });
                document.getElementById(`gallery-${year}`).classList.remove('hidden');
            });
        });

        // Für jede Jahresgalerie die Bildnavigation einrichten
        document.querySelectorAll('.gallery-container').forEach(gallery => {
            const thumbnails = gallery.querySelectorAll('.thumbnail');
            const mainImage = gallery.querySelector('.main-image');
            const prevButton = gallery.querySelector('.prev-button');
            const nextButton = gallery.querySelector('.next-button');
            
            let currentIndex = 0;
            
            if (thumbnails.length === 0) return;
            
            // Thumbnail-Klick
            thumbnails.forEach(thumb => {
                thumb.addEventListener('click', function() {
                    currentIndex = parseInt(this.getAttribute('data-index'));
                    updateMainImage();
                    updateThumbnailSelection();
                });
            });
            
            // Pfeil-Buttons
            if (prevButton && nextButton) {
                prevButton.addEventListener('click', function() {
                    currentIndex = (currentIndex - 1 + thumbnails.length) % thumbnails.length;
                    updateMainImage();
                    updateThumbnailSelection();
                    scrollToThumbnail();
                });
                
                nextButton.addEventListener('click', function() {
                    currentIndex = (currentIndex + 1) % thumbnails.length;
                    updateMainImage();
                    updateThumbnailSelection();
                    scrollToThumbnail();
                });
            }
            
            function updateMainImage() {
                if (mainImage && thumbnails[currentIndex]) {
                    mainImage.src = thumbnails[currentIndex].src;
                }
            }
            
            function updateThumbnailSelection() {
                thumbnails.forEach(thumb => {
                    thumb.classList.remove('ring-2', 'ring-primary');
                });
                thumbnails[currentIndex].classList.add('ring-2', 'ring-primary');
            }
            
            function scrollToThumbnail() {
                thumbnails[currentIndex].scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                    inline: 'center'
                });
            }
        });
    });
    </script>
</x-app-layout>