<x-app-layout title="Fotogalerie – Offizieller MADDRAX Fanclub e. V." description="Bilder von Veranstaltungen und Treffen des Fanclubs.">
    <x-member-page class="max-w-7xl space-y-8">
        @php
            $totalPhotos = collect($photos)->sum(fn ($items) => count($items));
        @endphp

        <x-ui.page-header
            eyebrow="Bilder aus dem Vereinsleben"
            title="Fotogalerie"
            description="Hier findest du Fotos von unseren Veranstaltungen aus den letzten Jahren. Wechsle zwischen den Jahrgängen und blättere direkt innerhalb jeder Galerie weiter."
        >
            <x-slot:actions>
                <div class="flex flex-wrap gap-2">
                    <span class="badge badge-primary badge-outline rounded-full px-3 py-3">{{ count($years) }} Jahrgänge</span>
                    <span class="badge badge-outline rounded-full px-3 py-3">{{ $totalPhotos }} Bilder</span>
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        <section class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(18rem,0.72fr)] xl:items-start">
            <x-ui.panel title="Galerieansicht" description="Wähle einen Jahrgang und nutze die Pfeile oder die Vorschaubilder, um durch die verfügbaren Fotos zu navigieren.">
                <div
                    x-data="{
                        activeYear: '{{ $activeYear }}',
                        galleries: @js(collect($years)->mapWithKeys(fn ($y) => [$y => ['index' => 0, 'count' => count($photos[$y] ?? [])]])),
                        get currentGallery() { return this.galleries[this.activeYear] ?? { index: 0, count: 0 } },
                        changeYear(year) {
                            this.activeYear = year;
                            this.$nextTick(() => this.updateMainImage());
                        },
                        prev() {
                            let gallery = this.currentGallery;
                            if (gallery.count < 2) return;
                            gallery.index = (gallery.index - 1 + gallery.count) % gallery.count;
                            this.updateMainImage();
                        },
                        next() {
                            let gallery = this.currentGallery;
                            if (gallery.count < 2) return;
                            gallery.index = (gallery.index + 1) % gallery.count;
                            this.updateMainImage();
                        },
                        goTo(index) {
                            this.currentGallery.index = index;
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
                            thumbs.forEach((thumb, index) => {
                                thumb.classList.toggle('ring-2', index === this.currentGallery.index);
                                thumb.classList.toggle('ring-primary', index === this.currentGallery.index);
                            });
                            thumbs[this.currentGallery.index]?.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                        }
                    }"
                    x-init="$nextTick(() => updateMainImage())"
                    class="space-y-6"
                >
                    <div class="flex flex-wrap gap-3" role="tablist" aria-label="Fotogalerie nach Jahrgängen">
                        @foreach($years as $year)
                            <button
                                type="button"
                                role="tab"
                                class="inline-flex items-center gap-3 rounded-full border px-4 py-2 text-sm font-medium transition"
                                :class="activeYear === '{{ $year }}'
                                    ? 'border-primary bg-primary text-primary-content shadow-lg shadow-primary/20'
                                    : 'border-base-content/10 bg-base-100 text-base-content hover:border-primary/25 hover:text-primary'"
                                :aria-selected="activeYear === '{{ $year }}' ? 'true' : 'false'"
                                @click="changeYear('{{ $year }}')"
                            >
                                <span>Fotos {{ $year }}</span>
                                <span class="rounded-full bg-base-100/15 px-2 py-1 text-xs font-semibold" :class="activeYear === '{{ $year }}' ? 'text-primary-content' : 'text-base-content/65'">{{ count($photos[$year] ?? []) }}</span>
                            </button>
                        @endforeach
                    </div>

                    @foreach($years as $year)
                        <section x-ref="gallery-{{ $year }}" x-show="activeYear === '{{ $year }}'" x-cloak class="space-y-5">
                            <div class="flex flex-wrap items-end justify-between gap-3 rounded-[1.5rem] border border-base-content/10 bg-base-100/72 px-4 py-4">
                                <div>
                                    <p class="text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-base-content/45">Jahrgang</p>
                                    <h2 class="mt-2 font-display text-2xl font-semibold tracking-tight text-base-content">{{ $year }}</h2>
                                </div>
                                <div class="text-sm leading-relaxed text-base-content/62">
                                    <p>{{ count($photos[$year] ?? []) }} Bilder in diesem Jahrgang</p>
                                    <p x-text="currentGallery.count > 0 ? `Bild ${currentGallery.index + 1} von ${currentGallery.count}` : 'Keine Bilder verfügbar'"></p>
                                </div>
                            </div>

                            <div class="relative overflow-hidden rounded-[1.75rem] border border-base-content/10 bg-[radial-gradient(circle_at_top,_rgba(255,255,255,0.6),_transparent_55%),linear-gradient(135deg,rgba(15,23,42,0.08),rgba(148,163,184,0.04))] p-3 sm:p-4">
                                <button @click="prev()" class="absolute left-3 top-1/2 z-10 inline-flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full border border-white/20 bg-slate-950/60 text-white backdrop-blur transition hover:bg-slate-950/80" aria-label="Vorheriges Bild">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 19l-7-7 7-7" />
                                    </svg>
                                </button>

                                <div class="main-image-container flex h-64 items-center justify-center overflow-hidden rounded-[1.35rem] bg-slate-950/8 sm:h-80 md:h-[28rem] lg:h-[34rem]">
                                    @if(isset($photos[$year]) && count($photos[$year]) > 0)
                                        <img src="{{ $photos[$year][0] }}" alt="Foto aus {{ $year }}" class="main-image max-h-full max-w-full rounded-[1.1rem] object-contain shadow-2xl shadow-slate-950/15">
                                    @else
                                        <div class="text-base-content">Keine Fotos für {{ $year }} verfügbar</div>
                                    @endif
                                </div>

                                <button @click="next()" class="absolute right-3 top-1/2 z-10 inline-flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full border border-white/20 bg-slate-950/60 text-white backdrop-blur transition hover:bg-slate-950/80" aria-label="Nächstes Bild">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5l7 7-7 7" />
                                    </svg>
                                </button>
                            </div>

                            <div class="thumbnails-container flex overflow-x-auto gap-3 pb-2">
                                @if(isset($photos[$year]))
                                    @foreach($photos[$year] as $index => $photoUrl)
                                        <img @if($index > 0) loading="lazy" @endif
                                            src="{{ $photoUrl }}"
                                            alt="Vorschaubild {{ $index + 1 }} aus {{ $year }}"
                                            class="thumbnail h-24 w-auto cursor-pointer rounded-[1rem] border border-white/20 object-cover shadow-lg shadow-slate-950/10 transition hover:-translate-y-0.5 {{ $index === 0 ? 'ring-2 ring-primary' : '' }}"
                                            @click="goTo({{ $index }})"
                                        >
                                    @endforeach
                                @endif
                            </div>
                        </section>
                    @endforeach
                </div>
            </x-ui.panel>

            <div class="space-y-6 xl:sticky xl:top-6">
                <x-ui.panel title="Jahre im Überblick" description="Jeder Jahrgang bündelt Fotos aus Treffen, Aktionen und Vereinsmomenten in einer gemeinsamen Galerie.">
                    <div class="space-y-3">
                        @foreach($years as $year)
                            <div class="flex items-center justify-between rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3 text-sm text-base-content/76 sm:text-base">
                                <div>
                                    <p class="font-medium text-base-content">Fotos {{ $year }}</p>
                                    <p class="text-sm text-base-content/58">{{ count($photos[$year] ?? []) }} Bilder verfügbar</p>
                                </div>
                                <span class="badge badge-outline rounded-full px-3 py-3">{{ $year }}</span>
                            </div>
                        @endforeach
                    </div>
                </x-ui.panel>

                <x-ui.panel title="Hinweise zur Galerie" description="Die Bilder werden aus den veröffentlichten Vereinsalben geladen. Falls ein Jahrgang noch im Aufbau ist, erscheinen automatisch Platzhalter.">
                    <ul class="grid gap-3 text-sm leading-relaxed text-base-content/76 sm:text-base">
                        <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Die Galerie springt beim Jahreswechsel automatisch auf das passende Hauptbild.</li>
                        <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Mit Pfeilen und Vorschaubildern lässt sich jede Serie direkt auf derselben Seite durchblättern.</li>
                        <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Fehlende Cloud-Bilder werden durch lokale Platzhalter abgefangen, damit die Seite stabil bleibt.</li>
                    </ul>
                </x-ui.panel>
            </div>
        </section>
    </x-member-page>
</x-app-layout>