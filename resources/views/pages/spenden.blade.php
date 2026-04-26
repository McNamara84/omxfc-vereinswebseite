@php
    $spendenWirkung = [
        [
            'eyebrow' => 'Fantreffen',
            'title' => 'Begegnungen finanzieren',
            'description' => 'Spenden helfen uns bei Räumen, Material und organisatorischen Kosten rund um unsere Community-Events.',
        ],
        [
            'eyebrow' => 'Infrastruktur',
            'title' => 'Webseite stabil halten',
            'description' => 'Server, Hosting und technische Werkzeuge für unsere Plattform laufen nicht kostenlos im Hintergrund.',
        ],
        [
            'eyebrow' => 'Projekte',
            'title' => 'Fanarbeit ermöglichen',
            'description' => 'Auch Vereins- und Community-Projekte profitieren von einer soliden finanziellen Basis.',
        ],
    ];

    $spendenHinweise = [
        [
            'text' => 'Die Spende läuft direkt über PayPal und kann ohne zusätzlichen Vereinszugang abgewickelt werden.',
        ],
        [
            'prefix' => 'Für Rückfragen rund um Spenden oder Mitgliedsbeiträge erreichst du uns unter ',
            'email' => 'kassenwart@maddrax-fanclub.de',
            'suffix' => '.',
        ],
        [
            'text' => 'Jeder Beitrag hilft, damit Fantreffen, Infrastruktur und Community-Angebote langfristig möglich bleiben.',
        ],
    ];
@endphp

<x-app-layout title="Spenden – Offizieller MADDRAX Fanclub e. V." description="Unterstütze unseren Fanclub finanziell für Fantreffen, Projekte und Serverkosten.">
    <x-public-page class="space-y-8">
        <x-ui.page-header
            eyebrow="Support für den Verein"
            title="Spenden"
            description="Der Offizielle MADDRAX Fanclub e. V. bietet Fans der postapokalyptischen Genre-Mix-Serie MADDRAX eine Plattform zum Austausch, für Projekte und für gemeinsame Veranstaltungen."
        >
            <x-slot:actions>
                <div class="flex flex-wrap gap-2">
                    <span class="badge badge-primary badge-outline rounded-full px-3 py-3">Fantreffen</span>
                    <span class="badge badge-outline rounded-full px-3 py-3">Serverkosten</span>
                    <span class="badge badge-outline rounded-full px-3 py-3">Fanprojekte</span>
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        <section class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(20rem,0.82fr)] xl:items-start">
            <div class="space-y-8">
                <x-ui.panel title="Was deine Spende ermöglicht" description="Spenden helfen uns bei der Finanzierung der jährlichen Fantreffen sowie der Serverkosten dieser Webseite.">
                    <div class="grid gap-4 md:grid-cols-3">
                        @foreach($spendenWirkung as $punkt)
                            <article class="rounded-3xl border border-base-content/10 bg-base-100/72 p-5">
                                <p class="text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-base-content/45">{{ $punkt['eyebrow'] }}</p>
                                <h2 class="mt-2 font-display text-xl font-semibold tracking-tight text-base-content">{{ $punkt['title'] }}</h2>
                                <p class="mt-2 text-sm leading-relaxed text-base-content/72">{{ $punkt['description'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </x-ui.panel>

                <x-ui.panel title="Transparenz und Kontakt" description="Wenn du Fragen zu Spenden oder Finanzthemen hast, bekommst du hier den direkten Draht.">
                    <ul class="grid gap-3">
                        @foreach($spendenHinweise as $hinweis)
                            <li class="flex items-start gap-3 rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3 text-sm leading-relaxed text-base-content/78">
                                <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/12 text-primary">{{ $loop->iteration }}</span>
                                <span>
                                    @if(isset($hinweis['email']))
                                        {{ $hinweis['prefix'] }}<a href="mailto:{{ $hinweis['email'] }}" class="link link-primary font-semibold">{{ $hinweis['email'] }}</a>{{ $hinweis['suffix'] }}
                                    @else
                                        {{ $hinweis['text'] }}
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.panel>
            </div>

            <div class="xl:sticky xl:top-6">
                <x-ui.panel title="Direkt per PayPal" description="Wenn du uns unmittelbar unterstützen willst, kannst du hier direkt den PayPal-Spendenbutton nutzen.">
                    <form action="https://www.paypal.com/donate" method="post" target="_top" class="flex flex-col items-center gap-4 text-center">
                        <input type="hidden" name="business" value="kassenwart@maddrax-fanclub.de" />
                        <input type="hidden" name="no_recurring" value="0" />
                        <input type="hidden" name="currency_code" value="EUR" />
                        <input type="image" src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donateCC_LG.gif" name="submit" alt="Spenden mit PayPal" class="w-48" />
                        <img alt="" src="https://www.paypal.com/en_DE/i/scr/pixel.gif" width="1" height="1" />
                        <p class="max-w-sm text-sm leading-relaxed text-base-content/72">
                            Der PayPal-Button leitet dich direkt zur sicheren Spendenseite weiter. Vereinsmitglieder und externe Unterstützer:innen können denselben Weg nutzen.
                        </p>
                    </form>
                </x-ui.panel>
            </div>
        </section>
    </x-public-page>
</x-app-layout>
