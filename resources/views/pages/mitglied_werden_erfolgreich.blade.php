@php
    $naechsteSchritte = [
        'Öffne die Bestätigungsmail und klicke auf den enthaltenen Link.',
        'Danach landet dein Antrag offiziell beim Vorstand zur manuellen Prüfung.',
        'Anschließend meldet sich der Kassenwart mit den Zahlungsinformationen bei dir.',
    ];
@endphp

<x-app-layout title="Antrag versendet – Offizieller MADDRAX Fanclub e. V." description="Bestätige deine E-Mail, damit wir deinen Mitgliedsantrag bearbeiten können.">
    <x-public-page class="max-w-4xl space-y-8">
        <x-ui.page-header
            eyebrow="Schritt 1 geschafft"
            title="Antrag erfolgreich eingereicht!"
            description="Wir haben dir eine E-Mail zur Bestätigung deiner Mailadresse geschickt. Erst mit diesem Klick können wir deinen Antrag weiterbearbeiten."
        >
            <x-slot:actions>
                <div class="flex flex-wrap gap-2">
                    <span class="badge badge-success badge-outline rounded-full px-3 py-3">Bestätigung per E-Mail</span>
                    <span class="badge badge-outline rounded-full px-3 py-3">manuelle Prüfung</span>
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        <section class="grid gap-6 lg:grid-cols-[minmax(0,1.15fr)_minmax(18rem,0.85fr)]">
            <x-ui.panel title="Was jetzt passiert" description="So geht es nach dem Absenden deines Antrags weiter.">
                <div class="space-y-3">
                    @foreach($naechsteSchritte as $schritt)
                        <div class="flex items-start gap-3 rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">
                            <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/12 text-primary">{{ $loop->iteration }}</span>
                            <p class="text-sm leading-relaxed text-base-content/78">{{ $schritt }}</p>
                        </div>
                    @endforeach
                </div>
            </x-ui.panel>

            <x-ui.panel title="Währenddessen sinnvoll" description="Wenn du die Wartezeit nutzen willst, sind das die besten nächsten Einstiege.">
                <div class="flex flex-col gap-3">
                    <a href="{{ route('home') }}" wire:navigate class="btn btn-primary rounded-full">
                        Zurück zur Startseite
                    </a>
                    <a href="{{ route('fantreffen.2026') }}" wire:navigate class="btn btn-ghost rounded-full bg-base-100/75">
                        Fantreffen 2026 ansehen
                    </a>
                    <a href="{{ route('satzung') }}" wire:navigate class="btn btn-ghost rounded-full bg-base-100/75">
                        Satzung noch einmal lesen
                    </a>
                </div>
            </x-ui.panel>
        </section>
    </x-public-page>
</x-app-layout>
