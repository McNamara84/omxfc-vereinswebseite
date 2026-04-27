@php
    $aktiveAgs = $ags->count();
    $agMitTreffen = $ags->filter(fn ($ag) => filled($ag->meeting_schedule))->count();
    $agMitKontakt = $ags->filter(fn ($ag) => filled($ag->email))->count();
@endphp

<x-app-layout title="Arbeitsgruppen – Offizieller MADDRAX Fanclub e. V." description="Überblick über alle Projektteams des Vereins.">
    <x-public-page class="space-y-8">
        <x-ui.page-header
            eyebrow="Projektteams im Verein"
            title="Arbeitsgruppen des OMXFC e.V."
            description="In den Arbeitsgruppen entstehen Fanprojekte, Vereinsorganisation und gemeinsame Formate. Hier siehst du, woran der OMXFC arbeitet, wer eine Gruppe leitet und wie du Kontakt aufnehmen kannst."
        >
            <x-slot:actions>
                <div class="flex flex-wrap gap-2">
                    <span class="badge badge-primary badge-outline rounded-full px-3 py-3">{{ $aktiveAgs }} aktive AGs</span>
                    <span class="badge badge-outline rounded-full px-3 py-3">{{ $agMitTreffen }} mit Rhythmus</span>
                    <span class="badge badge-outline rounded-full px-3 py-3">{{ $agMitKontakt }} mit Kontakt</span>
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        <section class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(19rem,0.8fr)] xl:items-start">
            <div class="space-y-6">
                @forelse($ags as $ag)
                    <article class="relative overflow-hidden rounded-[2rem] border border-base-content/10 bg-base-100/88 shadow-xl shadow-base-content/5 backdrop-blur">
                        <div class="absolute inset-x-0 top-0 h-px bg-linear-to-r from-primary/35 via-accent/25 to-transparent"></div>

                        <div class="grid gap-0 lg:grid-cols-[minmax(0,0.92fr)_minmax(0,1.08fr)]">
                            @if($ag->logo_path)
                                <div class="border-b border-base-content/10 bg-base-200/55 lg:border-b-0 lg:border-r">
                                    <img
                                        loading="lazy"
                                        src="{{ asset('storage/' . $ag->logo_path) }}"
                                        alt="Logo der {{ $ag->name }}"
                                        class="ag-logo h-full max-h-72 w-full object-cover"
                                    >
                                </div>
                            @endif

                            <div class="space-y-5 p-6 sm:p-7">
                                <div class="space-y-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="badge badge-outline rounded-full px-3 py-3">Arbeitsgruppe</span>
                                        @if($ag->meeting_schedule)
                                            <span class="badge badge-primary badge-soft rounded-full px-3 py-3">Treffen aktiv</span>
                                        @endif
                                    </div>

                                    <h2 class="font-display text-2xl font-semibold tracking-tight text-base-content sm:text-[2rem]">{{ $ag->name }}</h2>

                                    <p class="max-w-3xl text-sm leading-relaxed text-base-content/76 sm:text-base">
                                        {{ $ag->description ?: 'Diese Arbeitsgruppe bringt Mitglieder mit einem gemeinsamen Schwerpunkt zusammen und organisiert Austausch, Aufgaben und konkrete Vereinsbeiträge.' }}
                                    </p>
                                </div>

                                <dl class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                    <div class="rounded-[1.35rem] border border-base-content/10 bg-base-100/75 p-4">
                                        <dt class="text-[0.72rem] font-semibold uppercase tracking-[0.2em] text-base-content/46">AG-Leitung</dt>
                                        <dd class="mt-2 text-sm font-medium text-base-content sm:text-base">
                                            {{ $ag->owner?->name ?: 'Wird im Team abgestimmt' }}
                                        </dd>
                                    </div>

                                    <div class="rounded-[1.35rem] border border-base-content/10 bg-base-100/75 p-4">
                                        <dt class="text-[0.72rem] font-semibold uppercase tracking-[0.2em] text-base-content/46">Treffen</dt>
                                        <dd class="mt-2 text-sm font-medium text-base-content sm:text-base">
                                            {{ $ag->meeting_schedule ?: 'Nach Bedarf und Projektphase' }}
                                        </dd>
                                    </div>

                                    <div class="rounded-[1.35rem] border border-base-content/10 bg-base-100/75 p-4 sm:col-span-2 xl:col-span-1">
                                        <dt class="text-[0.72rem] font-semibold uppercase tracking-[0.2em] text-base-content/46">Kontakt</dt>
                                        <dd class="mt-2 text-sm font-medium text-base-content sm:text-base">
                                            @if($ag->email)
                                                <a href="mailto:{{ $ag->email }}" class="link link-primary break-all">{{ $ag->email }}</a>
                                            @else
                                                über das Mitgliedernetzwerk
                                            @endif
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    </article>
                @empty
                    <x-ui.panel title="Noch keine öffentlichen Arbeitsgruppen" description="Sobald neue Projektteams öffentlich vorgestellt werden, erscheinen sie hier mit Leitung, Kontaktdaten und Rhythmus.">
                        <div class="flex flex-wrap gap-3">
                            <a href="{{ route('mitglied.werden') }}" wire:navigate class="btn btn-primary rounded-full">Mitglied werden</a>
                            <a href="{{ route('termine') }}" wire:navigate class="btn btn-ghost rounded-full bg-base-100/75">Termine ansehen</a>
                        </div>
                    </x-ui.panel>
                @endforelse
            </div>

            <div class="space-y-6 xl:sticky xl:top-6">
                <x-ui.panel title="So funktionieren die AGs" description="Arbeitsgruppen sind die produktive Ebene des Vereins. Sie verbinden Austausch, Projektarbeit und Verlässlichkeit.">
                    <ul class="grid gap-3 text-sm leading-relaxed text-base-content/76 sm:text-base">
                        <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Jede AG bündelt ein klares Thema, damit Aufgaben nicht im allgemeinen Vereinsalltag untergehen.</li>
                        <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Leitung, Treffrhythmus und Kontaktweg sind sofort sichtbar, damit Interessierte direkt andocken können.</li>
                        <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Öffentliche Gruppen zeigen transparent, wo Zusammenarbeit bereits stattfindet und wo neue Unterstützung sinnvoll ist.</li>
                    </ul>
                </x-ui.panel>

                <x-ui.panel title="Mitmachen" description="Wenn dich ein Thema anspricht, ist der einfachste Einstieg über Mitgliedschaft, Termine oder direkte Kontaktaufnahme.">
                    <div class="flex flex-col gap-3">
                        <a href="{{ route('mitglied.werden') }}" wire:navigate class="btn btn-primary rounded-full">Mitglied werden</a>
                        <a href="{{ route('termine') }}" wire:navigate class="btn btn-ghost rounded-full bg-base-100/75">Nächste Termine ansehen</a>
                    </div>
                </x-ui.panel>
            </div>
        </section>
    </x-public-page>
</x-app-layout>
