@php
    $kalenderHighlights = [
        [
            'eyebrow' => 'Fantreffen',
            'title' => 'Events mit Community-Fokus',
            'description' => 'Hier landen größere Vereinsmomente wie Fantreffen, Panels und gemeinsame Aktionen.',
        ],
        [
            'eyebrow' => 'Arbeitsgruppen',
            'title' => 'Regelmäßige AG-Termine',
            'description' => 'Auch laufende Projekttermine und digitale Treffen bleiben im Kalender sichtbar.',
        ],
        [
            'eyebrow' => 'Verein',
            'title' => 'Wichtige Fristen im Blick',
            'description' => 'Mitgliederversammlungen und organisatorische Vereinstermine gehen nicht unter.',
        ],
    ];

    $kalenderTipps = [
        'Auf Desktop bekommst du die Monatsansicht, mobil automatisch die kompaktere Agenda-Ansicht.',
        'Über den Google-Kalender-Link kannst du den Vereinskalender direkt im Browser oder in deiner Kalender-App öffnen.',
        'Für das Fantreffen findest du zusätzlich alle wichtigen Details auf der eigenen Event-Seite.',
    ];
@endphp

<x-app-layout title="Termine – Offizieller MADDRAX Fanclub e. V." description="Aktuelle Vereinsveranstaltungen und Treffen im praktischen Kalender.">
    <x-public-page class="space-y-8">
        <x-ui.page-header
            eyebrow="Kalender & Veranstaltungen"
            title="Termine"
            description="Alle Vereinsveranstaltungen, Fantreffen und wiederkehrenden Community-Termine an einem Ort. Auf Desktop siehst du die Monatsansicht, mobil eine kompakte Agenda."
        >
            <x-slot:actions>
                <div class="flex flex-wrap gap-2">
                    <span class="badge badge-primary badge-outline rounded-full px-3 py-3">Fantreffen & AGs</span>
                    <span class="badge badge-outline rounded-full px-3 py-3">mobil optimiert</span>
                </div>

                <a href="{{ $calendarLink }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-sm rounded-full">
                    Kalender im Browser öffnen
                </a>
            </x-slot:actions>
        </x-ui.page-header>

        <section class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(18rem,0.82fr)] xl:items-start">
            <div class="space-y-8">
                <x-ui.panel title="Was im Kalender landet" description="Die wichtigsten Terminarten für Vereinsleben und Community-Projekte.">
                    <div class="grid gap-4 md:grid-cols-3">
                        @foreach($kalenderHighlights as $highlight)
                            <article class="rounded-3xl border border-base-content/10 bg-base-100/72 p-5">
                                <p class="text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-base-content/45">{{ $highlight['eyebrow'] }}</p>
                                <h2 class="mt-2 font-display text-xl font-semibold tracking-tight text-base-content">{{ $highlight['title'] }}</h2>
                                <p class="mt-2 text-sm leading-relaxed text-base-content/72">{{ $highlight['description'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </x-ui.panel>

                <x-ui.panel title="Vereinskalender" description="Der eingebettete Kalender ist die schnellste Übersicht für alle öffentlichen Vereins- und Community-Termine.">
                    <div class="hidden aspect-video overflow-hidden rounded-3xl border border-base-content/10 shadow-md md:block">
                        <iframe src="{{ $calendarUrl }}" class="h-full w-full border-0" frameborder="0" scrolling="no"></iframe>
                    </div>

                    <div class="h-[600px] overflow-hidden rounded-3xl border border-base-content/10 shadow-md md:hidden">
                        <iframe src="{{ $calendarUrlAgenda }}" class="h-full w-full border-0" frameborder="0" scrolling="no"></iframe>
                    </div>

                    <p class="mt-4 text-sm leading-relaxed text-base-content/72">
                        Wenn du lieber direkt im Browser oder in deiner Kalender-App arbeitest, kannst du den Vereinskalender jederzeit auch über
                        <a href="{{ $calendarLink }}" target="_blank" rel="noopener noreferrer" class="link link-primary font-semibold">
                            den Kalender im Browser
                        </a>
                        öffnen.
                    </p>
                </x-ui.panel>
            </div>

            <div class="space-y-6 xl:sticky xl:top-6">
                <x-ui.panel title="Kalender abonnieren" description="Die schnellsten Wege zurück in den Kalender oder direkt zu den wichtigsten Event-Seiten.">
                    <div class="flex flex-col gap-3">
                        <a href="{{ $calendarLink }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary rounded-full">Google Kalender</a>
                        <a href="{{ route('fantreffen.2026') }}" wire:navigate class="btn btn-ghost rounded-full bg-base-100/75">Fantreffen 2026</a>
                        <a href="{{ route('arbeitsgruppen.index') }}" wire:navigate class="btn btn-ghost rounded-full bg-base-100/75">Arbeitsgruppen ansehen</a>
                    </div>
                </x-ui.panel>

                <x-ui.panel title="So nutzt du die Ansicht" description="Drei kurze Hinweise, damit du schnell findest, was du brauchst.">
                    <ul class="grid gap-3">
                        @foreach($kalenderTipps as $tipp)
                            <li class="flex items-start gap-3 rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3 text-sm leading-relaxed text-base-content/78">
                                <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/12 text-primary">{{ $loop->iteration }}</span>
                                <span>{{ $tipp }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.panel>
            </div>
        </section>
    </x-public-page>
</x-app-layout>
