<x-app-layout title="Protokolle – Offizieller MADDRAX Fanclub e. V." description="Versammlungsprotokolle als PDF zum Download.">
    <x-member-page class="max-w-6xl space-y-8">
        @php
            $documentCount = collect($protokolle)->sum(fn ($dokumente) => count($dokumente));
        @endphp

        <div data-testid="page-title" class="sr-only">Protokolle</div>

        <x-ui.page-header
            eyebrow="Versammlungen und Beschlüsse"
            title="Protokolle"
            description="Hier liegen die Vereinsprotokolle als PDF nach Jahren sortiert. Öffne einen Jahrgang, um einzelne Dokumente direkt herunterzuladen."
        >
            <x-slot:actions>
                <div class="flex flex-wrap gap-2">
                    <span class="badge badge-primary badge-outline rounded-full px-3 py-3">{{ count($protokolle) }} Jahrgänge</span>
                    <span class="badge badge-outline rounded-full px-3 py-3">{{ $documentCount }} Dokumente</span>
                    <span class="badge badge-outline rounded-full px-3 py-3">PDF-Downloads</span>
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        <section class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(18rem,0.72fr)] xl:items-start">
            <x-ui.panel title="Archiv nach Jahren" description="Das neueste Jahr ist standardmäßig geöffnet. Weitere Jahrgänge lassen sich per Klick oder Tastatur ein- und ausklappen.">
                <div id="accordion" class="space-y-4">
                    @foreach($protokolle as $jahr => $dokumente)
                        <details class="group overflow-hidden rounded-3xl border border-base-content/10 bg-base-100/72 shadow-sm shadow-base-content/5"
                                 data-protokolle-accordion-item
                                 x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }"
                                 x-on:toggle="open = $el.open"
                                 @if($loop->first) open @endif>
                            <summary
                                id="accordion-trigger-{{ $jahr }}"
                                class="list-none flex w-full cursor-pointer items-center justify-between gap-4 px-5 py-4 text-left font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                                :aria-expanded="open ? 'true' : 'false'"
                                aria-controls="content-{{ $jahr }}"
                                role="button"
                            >
                                <span class="flex flex-col sm:flex-row sm:items-center sm:gap-2">
                                    <span>Protokolle {{ $jahr }}</span>
                                    <x-badge :value="count($dokumente) . ' ' . (count($dokumente) === 1 ? 'Dokument' : 'Dokumente')" class="badge-ghost badge-sm" icon="o-document-text" />
                                </span>
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-base-200/70 text-base-content/58 transition-transform group-open:-rotate-180" aria-hidden="true">
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m6 9 6 6 6-6" />
                                    </svg>
                                </span>
                                <span class="sr-only">Abschnitt Protokolle {{ $jahr }} umschalten</span>
                            </summary>

                            <div
                                id="content-{{ $jahr }}"
                                class="border-t border-base-content/10 px-4 py-4 sm:px-5"
                                role="region"
                                aria-labelledby="accordion-trigger-{{ $jahr }}"
                                :aria-hidden="open ? 'false' : 'true'"
                            >
                                <ul class="space-y-3">
                                    @foreach($dokumente as $protokoll)
                                        <li>
                                            <a
                                                href="{{ route('protokolle.download', $protokoll['datei']) }}"
                                                target="_blank"
                                                rel="noopener"
                                                class="inline-flex w-full items-start gap-3 rounded-[1.25rem] border border-base-content/10 bg-base-100/82 px-4 py-3 text-primary transition hover:border-primary/25 hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                                                x-data
                                                @click="$dispatch('mary-toast', { title: 'Download gestartet', description: 'Die PDF-Datei wird heruntergeladen…', position: 'toast-bottom toast-end', icon: 'o-arrow-down-tray', css: 'alert-info', timeout: 3000 })"
                                            >
                                                <x-icon name="o-document-text" class="mt-0.5 h-4 w-4 shrink-0" />
                                                <span class="text-sm leading-relaxed sm:text-base">{{ $protokoll['datum'] }} – {{ $protokoll['titel'] }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </details>
                    @endforeach
                </div>
            </x-ui.panel>

            <div class="space-y-6 xl:sticky xl:top-6">
                <x-ui.panel title="Was hier abgelegt ist" description="Die Sammlung bündelt zentrale Vereinsdokumente und bleibt chronologisch nach Jahren gegliedert.">
                    <ul class="grid gap-3 text-sm leading-relaxed text-base-content/76 sm:text-base">
                        <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Jahreshauptversammlungen und außerordentliche Mitgliederversammlungen sind getrennt nach Datum aufgeführt.</li>
                        <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Der aktuellste Jahrgang steht oben und ist direkt geöffnet.</li>
                        <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Jedes Dokument wird direkt als PDF in einem neuen Tab oder Download geöffnet.</li>
                    </ul>
                </x-ui.panel>

                <x-ui.panel title="Download-Hinweis" description="Beim Klick startet direkt der PDF-Abruf. Fehlt eine Datei im privaten Speicher, wirst du mit einer Fehlermeldung zurück auf diese Seite geleitet.">
                    <div class="grid gap-3 text-sm leading-relaxed text-base-content/76 sm:text-base">
                        <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">
                            <p class="font-medium text-base-content">Authentifizierter Zugriff</p>
                            <p class="mt-1">Die Protokolle bleiben nur für eingeloggte Mitglieder erreichbar.</p>
                        </div>
                        <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">
                            <p class="font-medium text-base-content">Direktes Feedback</p>
                            <p class="mt-1">Ein Toast informiert sofort darüber, dass der Download gestartet wurde.</p>
                        </div>
                    </div>
                </x-ui.panel>
            </div>
        </section>
    </x-member-page>
</x-app-layout>
