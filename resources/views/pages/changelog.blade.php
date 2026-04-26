<x-app-layout title="Changelog – Offizieller MADDRAX Fanclub e. V." description="Protokoll aller Aktualisierungen und Verbesserungen der Vereinswebseite des Offiziellen MADDRAX Fanclub e. V.">
    <x-public-page class="space-y-8">
        <x-ui.page-header
            eyebrow="Release- und Änderungsverlauf"
            title="Changelog"
            description="Auf dieser Seite werden sämtliche Änderungen an der Vereinswebseite des Offiziellen MADDRAX Fanclub e. V. dokumentiert. Neue Releases erscheinen oben, ältere Einträge bleiben zur Nachvollziehbarkeit erhalten."
        >
            <x-slot:actions>
                <div class="flex flex-wrap gap-2">
                    <span class="badge badge-primary badge-outline rounded-full px-3 py-3">laufend gepflegt</span>
                    <span class="badge badge-outline rounded-full px-3 py-3">neueste Version zuerst</span>
                    <span class="badge badge-outline rounded-full px-3 py-3">öffentliche Release-Notizen</span>
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        <section class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(18rem,0.78fr)] xl:items-start">
            <x-ui.panel title="Änderungen an der Vereinswebseite" description="Jede Version fasst Verbesserungen, neue Funktionen und Korrekturen in einem aufklappbaren Eintrag zusammen.">
                <livewire:changelog />
            </x-ui.panel>

            <div class="space-y-6 xl:sticky xl:top-6">
                <x-ui.panel title="Wie du den Changelog liest" description="Die Release-Notizen verwenden einfache Typmarker, damit Änderungen schneller einsortiert werden können.">
                    <div class="grid gap-3 text-sm leading-relaxed text-base-content/76 sm:text-base">
                        <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">
                            <span class="badge badge-success badge-soft rounded-full">NEW / ADDED</span>
                            <p class="mt-2">Neue Funktionen, neue Inhalte oder neue Einstiege.</p>
                        </div>
                        <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">
                            <span class="badge badge-info badge-soft rounded-full">IMPROVED / CHANGED</span>
                            <p class="mt-2">Überarbeitete Abläufe, Design-Updates oder strukturelle Verbesserungen.</p>
                        </div>
                        <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">
                            <span class="badge badge-error badge-soft rounded-full">FIXED</span>
                            <p class="mt-2">Fehlerbehebungen und verlässlicher gemachte bestehende Funktionen.</p>
                        </div>
                    </div>
                </x-ui.panel>

                <x-ui.panel title="Wofür das nützlich ist" description="Der Changelog macht sichtbare Produktpflege transparent und hilft, Änderungen auch nach einem späteren Einstieg nachzuvollziehen.">
                    <ul class="grid gap-3 text-sm leading-relaxed text-base-content/76 sm:text-base">
                        <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Mitglieder sehen auf einen Blick, was sich seit ihrem letzten Besuch geändert hat.</li>
                        <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Der Verein dokumentiert Releases nachvollziehbar und öffentlich.</li>
                        <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Rückfragen zu neuen Funktionen lassen sich leichter auf konkrete Versionen beziehen.</li>
                    </ul>
                </x-ui.panel>
            </div>
        </section>
    </x-public-page>
</x-app-layout>