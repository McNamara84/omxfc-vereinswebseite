<x-app-layout title="Fotogalerie – Offizieller MADDRAX Fanclub e. V." description="Bilder von Veranstaltungen und Treffen des Fanclubs.">
    <x-member-page class="max-w-6xl">
        <x-header title="Fotogalerie">
            <x-slot:subtitle>
                Hier findest du Fotos von unseren Veranstaltungen aus den letzten Jahren.
            </x-slot:subtitle>
        </x-header>

        <x-card shadow>

        <div x-data="{
            activeYear: '{{ $activeYear }}',
            galleries: @js(collect($years)->mapWithKeys(fn ($y) => [$y => ['index' => 0, 'count' => count($photos[$y] ?? [])]])),
            get currentGallery() { return this.galleries[this.activeYear] ?? { index: 0, count: 0 } },
            prev() {
                let g = this.currentGallery;
                if (g.count < 2) return;
                g.index = (g.index - 1 + g.count) % g.count;
                this.updateMainImage();
            },
            next() {
                let g = this.currentGallery;
                if (g.count < 2) return;
                g.index = (g.index + 1) % g.count;
                this.updateMainImage();
            },
            goTo(i) {
                this.currentGallery.index = i;
                this.updateMainImage();
            },
            updateMainImage() {
                const container = this.$refs['gallery-' + this.activeYear];
                if (!container) return;
                const thumbs = container.querySelectorAll('.thumbnail');
                const main = container.querySelector('.main-image');
                if (main && thumbs[this.currentGallery.index]) {
                    main.src = thumbs[this.currentGallery.index].src;
                }
                thumbs.forEach((t, i) => {
                    t.classList.toggle('ring-2', i === this.currentGallery.index);
                    t.classList.toggle('ring-primary', i === this.currentGallery.index);
                });
                thumbs[this.currentGallery.index]?.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            }
        }">

        <!-- Jahr-Tabs -->
        <div class="mb-8">
            <div class="border-b border-base-content/10">
                <nav class="flex -mb-px space-x-8" aria-label="Tabs">
                    @foreach($years as $year)
                        <button
                            class="py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap"
                            :class="activeYear === '{{ $year }}'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-base-content hover:text-base-content hover:border-base-content/30'"
                            @click="activeYear = '{{ $year }}'"
                        >
                            Fotos {{ $year }}
                        </button>
                    @endforeach
                </nav>
            </div>
        </div>

        <!-- Fotogalerien - pro Jahr eine -->
        @foreach($years as $year)
            <div x-ref="gallery-{{ $year }}" x-show="activeYear === '{{ $year }}'" class="gallery-container">
                <div class="relative">
                    <!-- Navigation-Buttons -->
                    <button @click="prev()" class="absolute left-0 top-1/2 transform -translate-y-1/2 bg-black bg-opacity-50 text-white p-2 rounded-r-lg z-10 hover:bg-opacity-70" aria-label="Vorheriges Bild">
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
                    
                    <button @click="next()" class="absolute right-0 top-1/2 transform -translate-y-1/2 bg-black bg-opacity-50 text-white p-2 rounded-l-lg z-10 hover:bg-opacity-70" aria-label="Nächstes Bild">
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
                                @click="goTo({{ $index }})"
                            >
                        @endforeach
                    @endif
                </div>
            </div>
        @endforeach
        </div>
        </x-card>
    </x-member-page>
</x-app-layout>