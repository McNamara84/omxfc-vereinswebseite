<x-app-layout title="Fotogalerie – Offizieller MADDRAX Fanclub e. V." description="Bilder von Veranstaltungen und Treffen des Fanclubs.">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pb-6 sm:pb-10 bg-gray-100 dark:bg-gray-800 rounded-lg shadow-sm">
        <h1 class="text-2xl sm:text-3xl font-bold text-[#8B0116] dark:text-[#ff4b63] mb-4 sm:mb-8">Fotogalerie</h1>

        <p class="mb-8 text-gray-700 dark:text-gray-300">
            Hier findest du Fotos von unseren Veranstaltungen aus den letzten Jahren.
        </p>

        <!-- Jahr-Tabs -->
        <div class="mb-8">
            <div class="border-b border-gray-200 dark:border-gray-600">
                <nav class="flex -mb-px space-x-8" aria-label="Tabs">
                    @foreach($years as $year)
                        <button 
                            class="jahr-tab py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap
                            {{ $year === $activeYear ? 'border-[#8B0116] dark:border-[#ff4b63] text-[#8B0116] dark:text-[#ff4b63]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 dark:hover:border-gray-300' }}"
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
                    <div class="main-image-container h-64 sm:h-80 md:h-96 lg:h-[500px] flex items-center justify-center mb-4 bg-gray-200 dark:bg-gray-700 rounded-lg overflow-hidden">
                        @if(isset($photos[$year]) && count($photos[$year]) > 0)
                            <img src="{{ $photos[$year][0] }}" alt="Foto {{ $year }}" class="main-image object-contain max-h-full max-w-full">
                        @else
                            <div class="text-gray-500 dark:text-gray-400">Keine Fotos für {{ $year }} verfügbar</div>
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
                            <img 
                                src="{{ $photoUrl }}" 
                                alt="Thumbnail {{ $index + 1 }}" 
                                class="thumbnail h-20 w-auto object-cover cursor-pointer rounded {{ $index === 0 ? 'ring-2 ring-[#8B0116] dark:ring-[#ff4b63]' : '' }}"
                                data-index="{{ $index }}"
                            >
                        @endforeach
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Jahr-Tab-Wechsel
        const jahresTabs = document.querySelectorAll('.jahr-tab');
        jahresTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const year = this.getAttribute('data-year');
                
                // Alle Tabs zurücksetzen
                jahresTabs.forEach(t => {
                    t.classList.remove('border-[#8B0116]', 'dark:border-[#ff4b63]', 'text-[#8B0116]', 'dark:text-[#ff4b63]');
                    t.classList.add('border-transparent', 'text-gray-500');
                });
                
                // Aktiven Tab setzen
                this.classList.remove('border-transparent', 'text-gray-500');
                this.classList.add('border-[#8B0116]', 'dark:border-[#ff4b63]', 'text-[#8B0116]', 'dark:text-[#ff4b63]');
                
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
                    thumb.classList.remove('ring-2', 'ring-[#8B0116]', 'dark:ring-[#ff4b63]');
                });
                thumbnails[currentIndex].classList.add('ring-2', 'ring-[#8B0116]', 'dark:ring-[#ff4b63]');
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