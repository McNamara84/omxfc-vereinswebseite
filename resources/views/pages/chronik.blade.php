<x-app-layout title="Chronik – Offizieller MADDRAX Fanclub e. V." description="Die wichtigsten Meilensteine des Offiziellen MADDRAX Fanclub e. V. seit seiner Gründung.">
    <x-public-page>
        <x-header title="Chronik des Offiziellen MADDRAX Fanclub e. V." class="mb-8" useH1 />
        <div class="relative border-l-4 border-primary pl-8 space-y-8">
            <div class="relative">
                <div class="absolute -left-[25px] top-2 bg-primary rounded-full w-3 h-3"></div>
                <time class="font-semibold text-lg">20. Mai 2023</time>
                <p class="mt-2">Der Verein <strong>Offizieller MADDRAX Fanclub</strong> wird gegründet. Die erste Version der Satzung wird beschlossen. 14 Gründungsmitglieder unterschreiben das Gründungsprotokoll und die Satzung. Holger wird zum 1. Vorsitzenden und Marko zum 2. Vorsitzenden gewählt. Zum Kassenwart wird Sebastian berufen.</p>
                <a href="#" class="block mt-3 max-w-xs rounded-md shadow cursor-pointer" @click.prevent="$dispatch('chronik-lightbox', { avif: '{{ asset('images/chronik/gruendungsversammlung.avif') }}', webp: '{{ asset('images/chronik/gruendungsversammlung.webp') }}', alt: 'Gründungsversammlung in Berlin 2023' })">
                    <picture>
                        <source type="image/avif" srcset="{{ asset('images/chronik/gruendungsversammlung.avif') }}">
                        <source type="image/webp" srcset="{{ asset('images/chronik/gruendungsversammlung.webp') }}">
                        <img loading="lazy" src="{{ asset('images/chronik/gruendungsversammlung.webp') }}" class="rounded-md" alt="Gründungsversammlung in Berlin 2023">
                    </picture>
                </a>
            </div>
            <div class="relative">
                <div class="absolute -left-[20px] top-2 bg-primary rounded-full w-3 h-3"></div>
                <time class="font-semibold text-lg">16. Oktober 2023</time>
                <p class="mt-2">Mit Lanthi tritt das zwanzigste Mitglied dem Verein bei. Gleichzeitig ist er auch das erste Mitglied, das nicht in Deutschland wohnhaft ist. Der OMXFC wird also international.</p>
            </div>
            <div class="relative">
                <div class="absolute -left-[20px] top-2 bg-primary rounded-full w-3 h-3"></div>
                <time class="font-semibold text-lg">26. Januar 2024</time>
                <p class="mt-2">Um den Verein im Vereinsregister eintragen lassen zu können, werden in einer außerordentlichen Online-Mitgliederversammlung notwendige Satzungsänderungen beschlossen.</p>
            </div>
            <div class="relative">
                <div class="absolute -left-[20px] top-2 bg-primary rounded-full w-3 h-3"></div>
                <time class="font-semibold text-lg">5. März 2024</time>
                <p class="mt-2">Der Verein wird in das zuständige Vereinsregister am Amtsgericht Potsdam eingetragen.</p>
            </div>
            <div class="relative">
                <div class="absolute -left-[20px] top-2 bg-primary rounded-full w-3 h-3"></div>
                <time class="font-semibold text-lg">11. Mai 2024</time>
                <p class="mt-2">Bei der ersten Jahreshauptversammlung wird ein neuer 2. Vorsitzender gewählt. Außerdem wird beschlossen, dass der Verein zukünftig das Fanhörbuch-Projekt EARDRAX übernehmen und unterstützen wird.</p>
                <a href="#" class="block mt-3 max-w-xs rounded-md shadow cursor-pointer" @click.prevent="$dispatch('chronik-lightbox', { avif: '{{ asset('images/chronik/jahreshauptversammlung2024.avif') }}', webp: '{{ asset('images/chronik/jahreshauptversammlung2024.webp') }}', alt: 'Jahreshauptversammlung in Köln 2024' })">
                    <picture>
                        <source type="image/avif" srcset="{{ asset('images/chronik/jahreshauptversammlung2024.avif') }}">
                        <source type="image/webp" srcset="{{ asset('images/chronik/jahreshauptversammlung2024.webp') }}">
                        <img loading="lazy" src="{{ asset('images/chronik/jahreshauptversammlung2024.webp') }}" class="rounded-md" alt="Jahreshauptversammlung in Köln 2024">
                    </picture>
                </a>
            </div>
            <div class="relative">
                <div class="absolute -left-[20px] top-2 bg-primary rounded-full w-3 h-3"></div>
                <time class="font-semibold text-lg">18. Mai 2024</time>
                <p class="mt-2">Mit dem Hörbuch 27 "Ruf des Blutes" startet bereits die achte Produktion von EARDRAX. Gestartet wurde dieses Projekt am 13. Juli 2021 mit der ersten Folge der Hörbuch-Umsetzung des MADDRAX Bandes 16 "Die Heiler".</p>
            </div>
            <div class="relative">
                <div class="absolute -left-[20px] top-2 bg-primary rounded-full w-3 h-3"></div>
                <time class="font-semibold text-lg">17. August 2024</time>
                <p class="mt-2">EARDRAX veröffentlicht die erste Folge der Hörbuch-Sonderedition APOKALYPSE. Der elfte und letzte Teil erscheint pünktlich zum 25-jährigen Jubiläum der Heftserie am 8. Februar 2025.</p>
            </div>
            <div class="relative">
                <div class="absolute -left-[20px] top-2 bg-primary rounded-full w-3 h-3"></div>
                <time class="font-semibold text-lg">22. November 2024</time>
                <p class="mt-2">Von der Mitgliederversammlung wird Tanja zur 1. Vorsitzenden und Arndt zum 2. Vorsitznden gewählt. Zum Kassenwart wird Markus berufen.</p>
            </div>
            <div class="relative">
                <div class="absolute -left-[20px] top-2 bg-primary rounded-full w-3 h-3"></div>
                <time class="font-semibold text-lg">7. Februar 2025</time>
                <p class="mt-2">In Aachen beginnt die erste MaddraxCon, die durch den Verein organisiert wurde mit einem Icebreaker bei feinstem Absinth</p>
                <a href="#" class="block mt-3 max-w-xs rounded-md shadow cursor-pointer" @click.prevent="$dispatch('chronik-lightbox', { avif: '{{ asset('images/chronik/maddraxcon2025-1.avif') }}', webp: '{{ asset('images/chronik/maddraxcon2025-1.webp') }}', alt: 'Fantreffen in Aachen 2025' })">
                    <picture>
                        <source type="image/avif" srcset="{{ asset('images/chronik/maddraxcon2025-1.avif') }}">
                        <source type="image/webp" srcset="{{ asset('images/chronik/maddraxcon2025-1.webp') }}">
                        <img loading="lazy" src="{{ asset('images/chronik/maddraxcon2025-1.webp') }}" class="rounded-md" alt="Fantreffen in Aachen 2025">
                    </picture>
                </a>
            </div>
            <div class="relative">
                <div class="absolute -left-[20px] top-2 bg-primary rounded-full w-3 h-3"></div>
                <time class="font-semibold text-lg">8. Februar 2025</time>
                <p class="mt-2">Im Jugendhaus St. Hubertus in Aachen, findet der zweite Tag der MaddraxCon zum 25jährigen Jubiläum der Heftserie statt. Gleichzeitig erscheint der <a href="https://de.maddraxikon.com/index.php?title=Quelle:MX654" target="_blank" rel="noopener noreferrer" class="link link-primary">Jubiläumsband 654 "Metamorphose"</a> von <a href="https://de.maddraxikon.com/index.php?title=Oliver_M%C3%BCller" target="_blank" rel="noopener noreferrer" class="link link-primary">Oliver Müller</a>, dessen Handlung passend zur Con ebenfalls in Aachen (Aarachne) spielt. An der Con nehmen Chefredakteur Michael "Mad Mike" Schönenbröcher und die Autoren Sascha Vennemann, Michael Edelbrock und Oliver Müller teil. Zeitgleich mit der Premiere auf Youtube wird den Conbesuchern der erste <a href="https://youtu.be/KfEpStNLYuM?si=oqNu66wsEt_ZYfux" target="_blank" rel="noopener noreferrer" class="link link-primary">Maddrax-Film "Der Kristall"</a> der Kurzfilmschmiede gezeigt.</p>
                <a href="#" class="block mt-3 max-w-xs rounded-md shadow cursor-pointer" @click.prevent="$dispatch('chronik-lightbox', { avif: '{{ asset('images/chronik/maddraxcon2025-2.avif') }}', webp: '{{ asset('images/chronik/maddraxcon2025-2.webp') }}', alt: 'Workshop auf der MaddraxCon 2025 in Aachen' })">
                    <picture>
                        <source type="image/avif" srcset="{{ asset('images/chronik/maddraxcon2025-2.avif') }}">
                        <source type="image/webp" srcset="{{ asset('images/chronik/maddraxcon2025-2.webp') }}">
                        <img loading="lazy" src="{{ asset('images/chronik/maddraxcon2025-2.webp') }}" class="rounded-md" alt="Workshop auf der MaddraxCon 2025 in Aachen">
                    </picture>
                </a>
            </div>
            <div class="relative">
                <div class="absolute -left-[20px] top-2 bg-primary rounded-full w-3 h-3"></div>
                <time class="font-semibold text-lg">9. Februar 2025</time>
                <p class="mt-2">Mit der Jahreshauptversammlung endet die MaddraxCon in Aachen. Der Verein begrüßt die neuen Mitglieder und hat aktuell 34 Vereinsmitglieder. Jo Zybell und Ian Rolf Hill werden als Ehrenmitglieder aufgenommen.</p>
                <a href="#" class="block mt-3 max-w-xs rounded-md shadow cursor-pointer" @click.prevent="$dispatch('chronik-lightbox', { avif: '{{ asset('images/chronik/jahreshauptversammlung2025.avif') }}', webp: '{{ asset('images/chronik/jahreshauptversammlung2025.webp') }}', alt: 'Jahreshauptversammlung in Aachen 2025' })">
                    <picture>
                        <source type="image/avif" srcset="{{ asset('images/chronik/jahreshauptversammlung2025.avif') }}">
                        <source type="image/webp" srcset="{{ asset('images/chronik/jahreshauptversammlung2025.webp') }}">
                        <img loading="lazy" src="{{ asset('images/chronik/jahreshauptversammlung2025.webp') }}" class="rounded-md" alt="Jahreshauptversammlung in Aachen 2025">
                    </picture>
                </a>
            </div>
            <div class="relative">
                <div class="absolute -left-[20px] top-2 bg-primary rounded-full w-3 h-3"></div>
                <time class="font-semibold text-lg">7. August 2025</time>
                <p class="mt-2">Der MADDRAX-Regionalstammtisch Berlin-Brandenburg findet zum ersten Mal statt.</p>
                <a href="#" class="block mt-3 max-w-xs rounded-md shadow cursor-pointer" @click.prevent="$dispatch('chronik-lightbox', { avif: '{{ asset('images/chronik/regionalstammtischbbb1.avif') }}', webp: '{{ asset('images/chronik/regionalstammtischbbb1.webp') }}', alt: 'Teilnehmer des ersten MADDRAX-Regionalstammtischs Berlin-Brandenburg 2025' })">
                    <picture>
                        <source type="image/avif" srcset="{{ asset('images/chronik/regionalstammtischbbb1.avif') }}">
                        <source type="image/webp" srcset="{{ asset('images/chronik/regionalstammtischbbb1.webp') }}">
                        <img loading="lazy" src="{{ asset('images/chronik/regionalstammtischbbb1.webp') }}" class="rounded-md" alt="Teilnehmer des ersten MADDRAX-Regionalstammtischs Berlin-Brandenburg 2025">
                    </picture>
                </a>
            </div>
            <!-- Weitere Ereignisse analog ergänzen -->
        </div>
        <div x-data="{ open: false, avif: '', webp: '', alt: '' }"
             @chronik-lightbox.window="avif = $event.detail.avif; webp = $event.detail.webp; alt = $event.detail.alt; open = true"
             @keydown.escape.window="open = false">
            <div x-show="open" x-transition.opacity
                 class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50"
                 @click.self="open = false"
                 role="dialog" aria-modal="true" style="display: none;">
                <div class="relative">
                    <button @click="open = false" class="absolute top-2 right-2 text-white text-2xl" aria-label="Bild schließen">&times;</button>
                    <picture>
                        <source type="image/avif" :srcset="avif" />
                        <source type="image/webp" :srcset="webp" />
                        <img :src="webp" :alt="alt" class="max-w-full max-h-screen rounded-md shadow-lg" />
                    </picture>
                </div>
            </div>
        </div>
    </x-public-page>
</x-app-layout>
