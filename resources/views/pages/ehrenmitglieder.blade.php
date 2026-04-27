@php
    $ehrenmitglieder = collect([
        [
            'name' => 'Michael Edelbrock',
            'image' => asset('images/ehrenmitglieder/michael-edelbrock.jpg'),
            'url' => 'https://de.maddraxikon.com/index.php?title=Michael_Edelbrock',
            'cta' => 'Mehr über Michael erfahren',
            'paragraphs' => [
                'Michael Edelbrock wurde 1980 geboren und beschäftigt sich am liebsten mit dicken Schmökern oder langen Sagen, sowohl in der klassischen Phantastik als auch in der Science-Fiction.',
                'Heute lebt er am Rande des Ruhrgebiets und schreibt dort seine Kurzgeschichten, Heftromane sowie eine phantastische Saga in Romanform.',
                'Er schreibt seit 2022 für die Maddrax-Serie und erhielt bisher zweimal den Leserpreis für den besten Roman eines Jahres, die „Goldene Taratze" im Jahr 2022 für Erschütterungen (MX 594) und 2025 für Die Gestade der Zeit (MX 628).',
            ],
        ],
        [
            'name' => 'Lucy Guth',
            'image' => asset('images/ehrenmitglieder/lucy-guth.jpg'),
            'url' => 'https://de.maddraxikon.com/index.php?title=Lucy_Guth',
            'cta' => 'Mehr über Lucy erfahren',
            'paragraphs' => [
                'Lucy Guth (Tanja Monique Bruske-Guth) wurde 1978 geboren und ist seit 2014 als Maddrax-Autorin dabei. In ihrem Hauptberuf arbeitet sie als Redakteurin bei der Gelnhäuser Neuen Zeitung.',
                'Sie veröffentlicht als Tanja Bruske Theaterstücke und Romane und als Lucy Guth auch für Perry Rhodan. 2023 erhielt sie den Leserpreis „Goldene Taratze" für ihren Roman Das Haus auf dem Hügel (MX 607).',
            ],
        ],
        [
            'name' => 'Ian Rolf Hill',
            'image' => asset('images/ehrenmitglieder/ian-rolf-hill.jpg'),
            'url' => 'https://de.maddraxikon.com/index.php?title=Ian_Rolf_Hill',
            'cta' => 'Mehr über Ian Rolf erfahren',
            'paragraphs' => [
                'Ian Rolf Hill (Florian Hilleberg) wurde 1980 geboren und ist seit 2016 für Maddrax aktiv. Für die Serie hat er eine Vielzahl an Romanen geschrieben und interessante Charaktere entwickelt.',
                'Er schreibt außerdem für John Sinclair. Seit 2024 hat er beschlossen, als Maddrax-Autor kürzer zu treten.',
            ],
        ],
        [
            'name' => 'Oliver Müller',
            'image' => asset('images/ehrenmitglieder/oliver-mueller.jpg'),
            'url' => 'https://de.maddraxikon.com/index.php?title=Oliver_Müller',
            'cta' => 'Mehr über Oliver erfahren',
            'paragraphs' => [
                'Oliver Müller wurde 1983 geboren und gab seinen Einstand bei Maddrax im Jahr 2014 mit Ein Käfig aus Zeit (MX 365). Neben seinem Hauptberuf veröffentlicht er viele Kurzgeschichten und schreibt Romane, auch für Professor Zamorra und John Sinclair.',
                '2021 erhielt er den Leserpreis „Goldenen Taratze“ für seinen Roman Der Giftplanet (MX 540).',
            ],
        ],
        [
            'name' => 'Michael Schönenbröcher',
            'image' => asset('images/ehrenmitglieder/michael-schoenenbröcher.jpg'),
            'url' => 'https://de.maddraxikon.com/index.php?title=Michael_Schönenbröcher',
            'cta' => 'Mehr über Mike erfahren',
            'paragraphs' => [
                'Michael Schönenbröcher (Mad Mike) wurde 1961 geboren, ist seit 1979 Lektor beim Bastei Verlag und seit 2000 alleiniger Betreuer von Maddrax. Die Serie, die in Zusammenarbeit mit den Autoren immer weiter ausgestaltet wird, geht auf seine Idee zurück.',
                'Neben der redaktionellen Arbeit schrieb er in der Vergangenheit auch selbst Romane für Maddrax. Außerdem entwirft er etliche der außergewöhnlichen Cover oder lässt sie von Künstlern speziell anfertigen.',
            ],
        ],
        [
            'name' => 'Jo Zybell',
            'image' => asset('images/ehrenmitglieder/jo-zybell.jpg'),
            'url' => 'https://de.maddraxikon.com/index.php?title=Jo_Zybell',
            'cta' => 'Mehr über Jo erfahren',
            'paragraphs' => [
                'Jo Zybell (Thomas Ziebula) wurde 1954 geboren und hat die Serie als Autor seit 2000 aktiv mitgestaltet. Von ihm wurde eine Vielzahl an Heftromanen und ergänzenden Hardcover-Büchern geschrieben, die das Maddrax-Universum ausloten.',
                'Er schrieb außerdem unter Pseudonym an verschiedenen Serien mit und hat mehrere Bücher verfasst. 2001 gewann er den Deutschen Phantastik-Preis als Bester Autor.',
            ],
        ],
    ]);
@endphp

<x-app-layout title="Ehrenmitglieder – Offizieller MADDRAX Fanclub e. V." description="Autoren der MADDRAX-Serie, die als Ehrenmitglieder des Fanclubs geehrt werden und sich besonders für die Fangemeinschaft engagieren.">
    <x-public-page class="space-y-8">
        <x-ui.page-header
            eyebrow="Autoren mit besonderem Stellenwert"
            title="Ehrenmitglieder"
            description="Diese Autorinnen und Autoren prägen das Maddraxiversum seit vielen Jahren. Der OMXFC würdigt ihr Engagement für Serie, Community und Fan-Kultur mit der Ehrenmitgliedschaft."
        >
            <x-slot:actions>
                <div class="flex flex-wrap gap-2">
                    <span class="badge badge-primary badge-outline rounded-full px-3 py-3">{{ $ehrenmitglieder->count() }} geehrte Stimmen</span>
                    <span class="badge badge-outline rounded-full px-3 py-3">Maddrax-Autor:innen</span>
                    <a href="{{ route('chronik') }}" wire:navigate class="btn btn-ghost btn-sm rounded-full bg-base-100/75">Zur Chronik</a>
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        <section class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(18rem,0.78fr)] xl:items-start">
            <div class="grid gap-6 md:grid-cols-2 2xl:grid-cols-3">
                @foreach($ehrenmitglieder as $mitglied)
                    <article class="group relative overflow-hidden rounded-[2rem] border border-base-content/10 bg-base-100/88 shadow-xl shadow-base-content/5 transition duration-200 hover:-translate-y-1 hover:shadow-2xl">
                        <div class="absolute inset-x-0 top-0 h-px bg-linear-to-r from-primary/35 via-accent/25 to-transparent"></div>

                        <div class="h-80 overflow-hidden bg-base-200/70">
                            <img
                                loading="lazy"
                                src="{{ $mitglied['image'] }}"
                                alt="{{ $mitglied['name'] }}"
                                class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]"
                            >
                        </div>

                        <div class="flex h-[calc(100%-20rem)] flex-col gap-4 p-5 sm:p-6">
                            <div class="space-y-2">
                                <span class="badge badge-outline rounded-full px-3 py-3">Ehrenmitglied</span>
                                <h2 class="font-display text-2xl font-semibold tracking-tight text-primary">{{ $mitglied['name'] }}</h2>
                            </div>

                            <div class="space-y-3 text-sm leading-relaxed text-base-content/76 sm:text-base">
                                @foreach($mitglied['paragraphs'] as $paragraph)
                                    <p>{{ $paragraph }}</p>
                                @endforeach
                            </div>

                            <div class="mt-auto pt-2">
                                <a href="{{ $mitglied['url'] }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-sm w-full rounded-full">
                                    {{ $mitglied['cta'] }}
                                </a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="space-y-6 xl:sticky xl:top-6">
                <x-ui.panel title="Warum Ehrenmitglieder?" description="Die Ehrung macht sichtbar, wer die Fangemeinschaft über Jahre hinweg besonders geprägt hat.">
                    <ul class="grid gap-3 text-sm leading-relaxed text-base-content/76 sm:text-base">
                        <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Die Liste würdigt kreative Beiträge zur Serie ebenso wie Nähe zur Community und Präsenz bei Fantreffen.</li>
                        <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Alle Karten folgen einer gemeinsamen Struktur und lassen sich dadurch künftig leichter erweitern und pflegen.</li>
                        <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Die externen Verweise führen direkt ins Maddraxikon für vertiefende Informationen zu Werk und Rollen in der Serie.</li>
                    </ul>
                </x-ui.panel>

                <x-ui.panel title="Bezug zur Community" description="Ehrenmitglieder stehen nicht isoliert neben dem Verein, sondern sind Teil seiner Geschichte und seiner Veranstaltungen.">
                    <div class="flex flex-col gap-3">
                        <a href="{{ route('chronik') }}" wire:navigate class="btn btn-primary rounded-full">Chronik ansehen</a>
                        <a href="{{ route('termine') }}" wire:navigate class="btn btn-ghost rounded-full bg-base-100/75">Termine entdecken</a>
                    </div>
                </x-ui.panel>
            </div>
        </section>
    </x-public-page>
</x-app-layout>
