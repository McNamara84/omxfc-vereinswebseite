<x-app-layout title="Meetings – Offizieller MADDRAX Fanclub e. V." description="Übersicht regelmäßiger AG-Termine und Stammtische.">
    <x-member-page class="max-w-6xl space-y-8">
        @php
            $meetingKeys = [
                'AG Maddraxikon' => 'maddraxikon',
                'AG EARDRAX' => 'fanhoerbuch',
                'AG MAPDRAX' => 'mapdrax',
                'CHATDRAX 2.0 - Der MADDRAX-Online-Stammtisch' => 'stammtisch',
            ];
            $regularMeetingCount = collect($meetings)->where('day', '!=', 'see_note')->count();
        @endphp

        <x-ui.page-header
            eyebrow="AG-Termine und Stammtische"
            title="Meetings"
            description="Hier findest du die regelmäßigen Online-Termine des Vereins. Die Übersicht zeigt, wann die nächste Runde stattfindet und führt dich direkt in das passende Zoom-Meeting."
        >
            <x-slot:actions>
                <div class="flex flex-wrap gap-2">
                    <span class="badge badge-primary badge-outline rounded-full px-3 py-3">{{ count($meetings) }} Formate</span>
                    <span class="badge badge-outline rounded-full px-3 py-3">{{ $regularMeetingCount }} regelmäßig geplant</span>
                    <span class="badge badge-outline rounded-full px-3 py-3">Zoom-Zugang direkt hier</span>
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        <section class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(18rem,0.72fr)] xl:items-start">
            <x-ui.panel title="Regelmäßige Termine" description="Alle Formate bleiben an einem Ort gebündelt. Für jeden Termin findest du Zeitpunkt, Rhythmus und den direkten Einstieg in den passenden Raum.">
                <div class="space-y-4">
                    @foreach($meetings as $meeting)
                        @php
                            $meetingKey = $meetingKeys[$meeting['name']] ?? null;
                            $isSpecialSchedule = $meeting['day'] === 'see_note';
                        @endphp

                        <article class="rounded-[1.5rem] border border-base-content/10 bg-base-100/72 px-5 py-5 shadow-sm shadow-base-content/5">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div class="space-y-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="font-display text-xl font-semibold tracking-tight text-base-content">{{ $meeting['name'] }}</h2>

                                        @if($isSpecialSchedule)
                                            <span class="badge badge-warning badge-soft rounded-full">variabler Termin</span>
                                        @else
                                            <span class="badge badge-success badge-soft rounded-full">monatlicher Termin</span>
                                        @endif
                                    </div>

                                    <div class="space-y-2 text-sm leading-relaxed text-base-content/72 sm:text-base">
                                        @if($isSpecialSchedule)
                                            <p><strong>Wann:</strong> Jeden zweiten Dienstag nach einem Roman (Beginn: {{ $meeting['time_from'] }})</p>
                                        @else
                                            <p><strong>Nächster Termin:</strong> {{ $meeting['next']->translatedFormat('l, d.m.Y') }}</p>
                                            <p><strong>Zeitfenster:</strong> {{ $meeting['time_from'] }} bis {{ $meeting['time_to'] }}</p>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex shrink-0 items-center">
                                    @if($meetingKey)
                                        <form method="POST" action="{{ route('meetings.redirect') }}">
                                            @csrf
                                            <input type="hidden" name="meeting" value="{{ $meetingKey }}">
                                            <x-button type="submit" label="Zoom-Meeting betreten" icon="o-video-camera" class="btn-primary btn-sm" />
                                        </form>
                                    @else
                                        <span class="inline-flex items-center rounded-full border border-base-content/10 bg-base-200/70 px-4 py-2 text-sm font-medium text-base-content/50">Link folgt</span>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </x-ui.panel>

            <div class="space-y-6 xl:sticky xl:top-6">
                <x-ui.panel title="Wie die Termine laufen" description="Die AGs treffen sich überwiegend in kurzen, planbaren Zoom-Slots. So bleiben Zusammenarbeit und Einstieg auch zwischen Präsenztreffen niedrigschwellig.">
                    <ul class="grid gap-3 text-sm leading-relaxed text-base-content/76 sm:text-base">
                        <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Regelmäßige AG-Termine zeigen immer den nächsten konkreten Termin an.</li>
                        <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Der Stammtisch läuft flexibel nach Romanterminen und wird deshalb als Hinweis statt mit festem Datum dargestellt.</li>
                        <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Der Button führt direkt in den jeweils passenden Zoom-Raum der Gruppe.</li>
                    </ul>
                </x-ui.panel>

                <x-ui.panel title="Formate im Überblick" description="Die Meeting-Seite bündelt Arbeitsgruppen und Community-Termine, damit laufende Projekte schnell erreichbar bleiben.">
                    <div class="grid gap-3 text-sm leading-relaxed text-base-content/76 sm:text-base">
                        <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">
                            <p class="font-medium text-base-content">Projektarbeit</p>
                            <p class="mt-1">Maddraxikon, EARDRAX und MAPDRAX haben feste Slots für ihre laufenden Aufgaben.</p>
                        </div>
                        <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">
                            <p class="font-medium text-base-content">Stammtisch</p>
                            <p class="mt-1">CHATDRAX 2.0 ergänzt die AGs um einen offenen Community-Termin.</p>
                        </div>
                    </div>
                </x-ui.panel>
            </div>
        </section>
    </x-member-page>
</x-app-layout>