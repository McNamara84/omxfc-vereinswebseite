@php
    $mitgliedHighlights = [
        [
            'eyebrow' => 'Community',
            'title' => 'Direkter Austausch',
            'description' => 'Du bist nicht nur Leser:in, sondern Teil einer aktiven Fan-Community mit Gesprächen, Projekten und Treffen.',
        ],
        [
            'eyebrow' => 'Events',
            'title' => 'Fantreffen inklusive',
            'description' => 'Mitglieder nehmen kostenlos an den jährlichen Fantreffen teil und kommen leichter mit Autor:innen ins Gespräch.',
        ],
        [
            'eyebrow' => 'Mitmachen',
            'title' => 'Projekte mit Wirkung',
            'description' => 'Vom Maddraxikon bis zu Hörbuch- und Kartenprojekten kannst du dich direkt in laufende AGs einbringen.',
        ],
    ];

    $mitgliedAblauf = [
        [
            'title' => 'Online-Antrag absenden',
            'description' => 'Du füllst den Antrag aus, legst dein Passwort fest und bestätigst die Satzung.',
        ],
        [
            'title' => 'E-Mail bestätigen',
            'description' => 'Wir schicken dir direkt danach einen Bestätigungslink, damit wir deine Adresse sicher zuordnen können.',
        ],
        [
            'title' => 'Prüfung und Freischaltung',
            'description' => 'Nach Prüfung durch den Vorstand und den Infos vom Kassenwart bekommst du Zugang zum internen Mitgliederbereich.',
        ],
    ];

    $mitgliedInfos = [
        'Der Mitgliedsbeitrag ist ab 12 € pro Jahr frei wählbar und kann später im Mitgliederbereich angepasst werden.',
        'Die Adresse wird für Vereinskommunikation, Postversand und die interne Mitgliederkarte benötigt.',
        'Wenn du erst einmal reinschnuppern willst, kannst du danach direkt die öffentlichen Projekte und das Fantreffen entdecken.',
    ];
@endphp

<x-app-layout title="Mitglied werden – Offizieller MADDRAX Fanclub e. V." description="Online-Antrag zur Aufnahme in den Fanclub der MADDRAX-Romanserie.">
    <x-public-page class="space-y-8">
        <x-ui.page-header
            eyebrow="Mitgliedschaft im OMXFC"
            title="Mitglied werden"
            description="Werde Teil einer aktiven MADDRAX-Community mit Fantreffen, Fanprojekten und einem internen Mitgliederbereich. Den Antrag kannst du direkt online ausfüllen."
            data-testid="mitglied-werden-header"
        >
            <x-slot:actions>
                <div class="flex flex-wrap gap-2">
                    <span class="badge badge-primary badge-outline rounded-full px-3 py-3">ab 12 € pro Jahr</span>
                    <span class="badge badge-outline rounded-full px-3 py-3">Fantreffen kostenlos</span>
                    <span class="badge badge-outline rounded-full px-3 py-3">Projekte & AGs</span>
                </div>

                <a href="{{ route('satzung') }}" wire:navigate class="btn btn-ghost btn-sm rounded-full bg-base-100/75">
                    Satzung ansehen
                </a>
            </x-slot:actions>
        </x-ui.page-header>

        <section class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(22rem,0.92fr)] xl:items-start">
            <div class="order-2 space-y-8 xl:order-1">
                <x-ui.panel title="Was du direkt bekommst" description="Der Verein bietet dir mehr als nur ein Formular und eine Bestätigungsmail.">
                    <div class="grid gap-4 md:grid-cols-3">
                        @foreach($mitgliedHighlights as $highlight)
                            <article class="rounded-[1.5rem] border border-base-content/10 bg-base-100/72 p-5">
                                <p class="text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-base-content/45">{{ $highlight['eyebrow'] }}</p>
                                <h2 class="mt-2 font-display text-xl font-semibold tracking-tight text-base-content">{{ $highlight['title'] }}</h2>
                                <p class="mt-2 text-sm leading-relaxed text-base-content/72">{{ $highlight['description'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </x-ui.panel>

                <x-ui.panel title="So läuft dein Einstieg ab" description="In drei klaren Schritten vom Antrag bis zur Freischaltung.">
                    <div class="space-y-4">
                        @foreach($mitgliedAblauf as $schritt)
                            <article class="flex gap-4 rounded-[1.5rem] border border-base-content/10 bg-base-100/72 p-4">
                                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/12 font-display text-lg font-semibold text-primary">
                                    {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}
                                </span>
                                <div class="space-y-1">
                                    <h2 class="font-semibold text-base-content">{{ $schritt['title'] }}</h2>
                                    <p class="text-sm leading-relaxed text-base-content/72">{{ $schritt['description'] }}</p>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </x-ui.panel>

                <x-ui.panel title="Gut zu wissen" description="Die wichtigsten Rahmenbedingungen vor deinem Antrag.">
                    <ul class="grid gap-3">
                        @foreach($mitgliedInfos as $info)
                            <li class="flex items-start gap-3 rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3 text-sm leading-relaxed text-base-content/78">
                                <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-success/12 text-success">✓</span>
                                <span>{{ $info }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.panel>
            </div>

            <div class="order-1 xl:order-2 xl:sticky xl:top-6">
                <x-ui.panel eyebrow="Online-Antrag" title="Dein Antrag in wenigen Minuten" description="Fülle die Pflichtfelder aus, bestätige die Satzung und schicke den Antrag direkt digital an den Verein.">
                    <livewire:mitglied-werden-form />
                </x-ui.panel>
            </div>
        </section>
    </x-public-page>
</x-app-layout>
