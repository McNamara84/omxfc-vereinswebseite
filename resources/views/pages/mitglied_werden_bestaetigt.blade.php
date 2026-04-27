@php
    $pruefAblauf = [
        'Dein Antrag liegt jetzt beim Vorstand und wird manuell geprüft.',
        'Vor der finalen Freischaltung bekommst du die Zahlungsdaten für deinen gewählten Mitgliedsbeitrag.',
        'Sobald Prüfung und Zahlung abgeschlossen sind, erhältst du eine weitere E-Mail mit den nächsten Zugangsinformationen.',
    ];
@endphp

<x-app-layout title="Antrag bestätigt – Offizieller MADDRAX Fanclub e. V." description="Dein Mitgliedsantrag wurde übermittelt und wird vom Vorstand geprüft.">
    <x-public-page class="max-w-4xl space-y-8">
        <x-ui.page-header
            eyebrow="Bestätigung eingegangen"
            title="Vielen Dank für deine Bestätigung!"
            description="Dein Mitgliedschaftsantrag wurde erfolgreich an den Vorstand übermittelt und wartet jetzt auf die manuelle Prüfung."
        >
            <x-slot:actions>
                <div class="flex flex-wrap gap-2">
                    <span class="badge badge-primary badge-outline rounded-full px-3 py-3">Prüfung durch Vorstand</span>
                    <span class="badge badge-outline rounded-full px-3 py-3">weitere Mail folgt</span>
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        <section class="grid gap-6 lg:grid-cols-[minmax(0,1.1fr)_minmax(18rem,0.9fr)]">
            <x-ui.panel title="Wie es jetzt weitergeht" description="Die letzten Schritte bis zur vollständigen Freischaltung im Verein.">
                <div class="space-y-3">
                    @foreach($pruefAblauf as $schritt)
                        <div class="flex items-start gap-3 rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">
                            <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/12 text-primary">{{ $loop->iteration }}</span>
                            <p class="text-sm leading-relaxed text-base-content/78">{{ $schritt }}</p>
                        </div>
                    @endforeach
                </div>
            </x-ui.panel>

            <x-ui.panel title="Bis dahin" description="Du musst gerade nichts weiter tun außer ein wenig Geduld mitbringen.">
                <p class="text-sm leading-relaxed text-base-content/78">
                    Die Prüfung geschieht bewusst manuell, damit wir neue Anträge sauber zuordnen und dir die passenden Informationen zum Mitgliedsbeitrag schicken können.
                </p>

                <div class="mt-4 flex flex-col gap-3">
                    <a href="{{ route('home') }}" wire:navigate class="btn btn-primary rounded-full">Zurück zur Startseite</a>
                    <a href="{{ route('fantreffen.2026') }}" wire:navigate class="btn btn-ghost rounded-full bg-base-100/75">Fantreffen entdecken</a>
                </div>
            </x-ui.panel>
        </section>
    </x-public-page>
</x-app-layout>
