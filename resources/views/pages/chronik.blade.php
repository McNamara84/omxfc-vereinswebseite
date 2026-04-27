<x-app-layout title="Chronik – Offizieller MADDRAX Fanclub e. V." description="Die wichtigsten Meilensteine des Offiziellen MADDRAX Fanclub e. V. seit seiner Gründung.">
    <x-public-page class="space-y-8">
        <x-ui.page-header
            eyebrow="Vereinsgeschichte seit 2023"
            title="Chronik des Offiziellen MADDRAX Fanclub e. V."
            description="Die wichtigsten Meilensteine vom Gründungsmoment über die ersten Fantreffen bis zu den jüngsten Community-Formaten. Die Chronik zeigt, wie aus Fan-Austausch Schritt für Schritt aktives Vereinsleben wurde."
        >
            <x-slot:actions>
                <div class="flex flex-wrap gap-2">
                    <span class="badge badge-primary badge-outline rounded-full px-3 py-3">seit 2023</span>
                    <span class="badge badge-outline rounded-full px-3 py-3">Fantreffen & Vorstand</span>
                    <span class="badge badge-outline rounded-full px-3 py-3">mit Fotomomenten</span>
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        <section class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(19rem,0.82fr)] xl:items-start">
            <x-ui.panel title="Meilensteine des Vereins" description="Die Chronik folgt den wichtigsten Ereignissen in zeitlicher Reihenfolge und zeigt, wie sich der OMXFC organisatorisch und als Community entwickelt hat.">
                <div class="relative space-y-6 pl-10 before:absolute before:bottom-2 before:left-3 before:top-2 before:w-px before:bg-primary/25">
                    <x-chronik-entry
                        date="20. Mai 2023"
                        :avif="asset('images/chronik/gruendungsversammlung.avif')"
                        :webp="asset('images/chronik/gruendungsversammlung.webp')"
                        alt="Gründungsversammlung in Berlin 2023"
                    >
                        <p>Der Verein <strong>Offizieller MADDRAX Fanclub</strong> wird gegründet. Die erste Version der Satzung wird beschlossen. 14 Gründungsmitglieder unterschreiben das Gründungsprotokoll und die Satzung. Holger wird zum 1. Vorsitzenden und Marko zum 2. Vorsitzenden gewählt. Zum Kassenwart wird Sebastian berufen.</p>
                    </x-chronik-entry>

                    <x-chronik-entry date="16. Oktober 2023">
                        <p>Mit Lanthi tritt das zwanzigste Mitglied dem Verein bei. Gleichzeitig ist er auch das erste Mitglied, das nicht in Deutschland wohnhaft ist. Der OMXFC wird also international.</p>
                    </x-chronik-entry>

                    <x-chronik-entry date="26. Januar 2024">
                        <p>Um den Verein im Vereinsregister eintragen lassen zu können, werden in einer außerordentlichen Online-Mitgliederversammlung notwendige Satzungsänderungen beschlossen.</p>
                    </x-chronik-entry>

                    <x-chronik-entry date="5. März 2024">
                        <p>Der Verein wird in das zuständige Vereinsregister am Amtsgericht Potsdam eingetragen.</p>
                    </x-chronik-entry>

                    <x-chronik-entry
                        date="11. Mai 2024"
                        :avif="asset('images/chronik/jahreshauptversammlung2024.avif')"
                        :webp="asset('images/chronik/jahreshauptversammlung2024.webp')"
                        alt="Jahreshauptversammlung in Köln 2024"
                    >
                        <p>Bei der ersten Jahreshauptversammlung wird ein neuer 2. Vorsitzender gewählt. Außerdem wird beschlossen, dass der Verein zukünftig das Fanhörbuch-Projekt EARDRAX übernehmen und unterstützen wird.</p>
                    </x-chronik-entry>

                    <x-chronik-entry date="18. Mai 2024">
                        <p>Mit dem Hörbuch 27 "Ruf des Blutes" startet bereits die achte Produktion von EARDRAX. Gestartet wurde dieses Projekt am 13. Juli 2021 mit der ersten Folge der Hörbuch-Umsetzung des MADDRAX Bandes 16 "Die Heiler".</p>
                    </x-chronik-entry>

                    <x-chronik-entry date="17. August 2024">
                        <p>EARDRAX veröffentlicht die erste Folge der Hörbuch-Sonderedition APOKALYPSE. Der elfte und letzte Teil erscheint pünktlich zum 25-jährigen Jubiläum der Heftserie am 8. Februar 2025.</p>
                    </x-chronik-entry>

                    <x-chronik-entry date="22. November 2024">
                        <p>Von der Mitgliederversammlung wird Tanja zur 1. Vorsitzenden und Arndt zum 2. Vorsitznden gewählt. Zum Kassenwart wird Markus berufen.</p>
                    </x-chronik-entry>

                    <x-chronik-entry
                        date="7. Februar 2025"
                        :avif="asset('images/chronik/maddraxcon2025-1.avif')"
                        :webp="asset('images/chronik/maddraxcon2025-1.webp')"
                        alt="Fantreffen in Aachen 2025"
                    >
                        <p>In Aachen beginnt die erste MaddraxCon, die durch den Verein organisiert wurde, mit einem Icebreaker bei feinstem Absinth.</p>
                    </x-chronik-entry>

                    <x-chronik-entry
                        date="8. Februar 2025"
                        :avif="asset('images/chronik/maddraxcon2025-2.avif')"
                        :webp="asset('images/chronik/maddraxcon2025-2.webp')"
                        alt="Workshop auf der MaddraxCon 2025 in Aachen"
                    >
                        <p>Im Jugendhaus St. Hubertus in Aachen findet der zweite Tag der MaddraxCon zum 25-jährigen Jubiläum der Heftserie statt. Gleichzeitig erscheint der <a href="https://de.maddraxikon.com/index.php?title=Quelle:MX654" target="_blank" rel="noopener noreferrer" class="link link-primary">Jubiläumsband 654 "Metamorphose"</a> von <a href="https://de.maddraxikon.com/index.php?title=Oliver_M%C3%BCller" target="_blank" rel="noopener noreferrer" class="link link-primary">Oliver Müller</a>, dessen Handlung passend zur Con ebenfalls in Aachen spielt.</p>
                        <p>An der Con nehmen Chefredakteur Michael "Mad Mike" Schönenbröcher und die Autoren Sascha Vennemann, Michael Edelbrock und Oliver Müller teil. Zeitgleich mit der Premiere auf YouTube wird den Besucher:innen der erste <a href="https://youtu.be/KfEpStNLYuM?si=oqNu66wsEt_ZYfux" target="_blank" rel="noopener noreferrer" class="link link-primary">Maddrax-Film "Der Kristall"</a> der Kurzfilmschmiede gezeigt.</p>
                    </x-chronik-entry>

                    <x-chronik-entry
                        date="9. Februar 2025"
                        :avif="asset('images/chronik/jahreshauptversammlung2025.avif')"
                        :webp="asset('images/chronik/jahreshauptversammlung2025.webp')"
                        alt="Jahreshauptversammlung in Aachen 2025"
                    >
                        <p>Mit der Jahreshauptversammlung endet die MaddraxCon in Aachen. Der Verein begrüßt die neuen Mitglieder und hat aktuell 34 Vereinsmitglieder. Jo Zybell und Ian Rolf Hill werden als Ehrenmitglieder aufgenommen.</p>
                    </x-chronik-entry>

                    <x-chronik-entry
                        date="7. August 2025"
                        :avif="asset('images/chronik/regionalstammtischbbb1.avif')"
                        :webp="asset('images/chronik/regionalstammtischbbb1.webp')"
                        alt="Teilnehmer des ersten MADDRAX-Regionalstammtischs Berlin-Brandenburg 2025"
                    >
                        <p>Der MADDRAX-Regionalstammtisch Berlin-Brandenburg findet zum ersten Mal statt.</p>
                    </x-chronik-entry>
                </div>
            </x-ui.panel>

            <div class="space-y-6 xl:sticky xl:top-6">
                <x-ui.panel title="Was die Chronik zeigt" description="Die Timeline ist mehr als ein Rückblick auf Termine. Sie macht den Aufbau des Vereins und der Community sichtbar.">
                    <ul class="grid gap-3">
                        <li class="flex items-start gap-3 rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3 text-sm leading-relaxed text-base-content/78">
                            <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/12 text-primary">1</span>
                            <span>Von der Gründung über Satzungsänderungen bis zu Vorstandswechseln bleibt die Vereinsentwicklung nachvollziehbar.</span>
                        </li>
                        <li class="flex items-start gap-3 rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3 text-sm leading-relaxed text-base-content/78">
                            <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/12 text-primary">2</span>
                            <span>EARDRAX, MaddraxCon und Regionalstammtisch zeigen, wie aus Lesen aktive Fanarbeit und echte Begegnungen werden.</span>
                        </li>
                        <li class="flex items-start gap-3 rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3 text-sm leading-relaxed text-base-content/78">
                            <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/12 text-primary">3</span>
                            <span>Die Bilder in der Lightbox liefern zusätzliche Momente aus der Vereinsgeschichte, ohne die Timeline visuell zu überladen.</span>
                        </li>
                    </ul>
                </x-ui.panel>

                <x-ui.panel title="Heute" description="Die Chronik endet nicht in der Vergangenheit, sondern führt direkt zu den nächsten Community-Momenten.">
                    <div class="flex flex-col gap-3">
                        <a href="{{ route('termine') }}" wire:navigate class="btn btn-primary rounded-full">Aktuelle Termine ansehen</a>
                        <a href="{{ route('fantreffen.2026') }}" wire:navigate class="btn btn-ghost rounded-full bg-base-100/75">Fantreffen 2026 entdecken</a>
                    </div>
                </x-ui.panel>
            </div>
        </section>

        <div x-data="{ open: false, avif: '', webp: '', alt: '' }"
             @chronik-lightbox.window="avif = $event.detail.avif; webp = $event.detail.webp; alt = $event.detail.alt; open = true"
             @keydown.escape.window="open = false">
            <div x-show="open" x-transition.opacity
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 px-4 backdrop-blur-sm"
                 @click.self="open = false"
                 role="dialog" aria-modal="true" aria-labelledby="chronik-lightbox-title" style="display: none;">
                <div class="relative max-w-5xl">
                    <h2 id="chronik-lightbox-title" class="sr-only" x-text="alt || 'Bildansicht'"></h2>
                    <button @click="open = false" class="absolute right-3 top-3 inline-flex h-10 w-10 items-center justify-center rounded-full bg-black/50 text-2xl text-white backdrop-blur" aria-label="Bild schließen">&times;</button>
                    <picture>
                        <source type="image/avif" :srcset="avif" />
                        <source type="image/webp" :srcset="webp" />
                        <img :src="webp" :alt="alt" class="max-h-[90vh] max-w-full rounded-[1.5rem] shadow-2xl" />
                    </picture>
                </div>
            </div>
        </div>
    </x-public-page>
</x-app-layout>
