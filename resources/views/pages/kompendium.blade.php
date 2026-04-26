<x-app-layout title="Kompendium – Offizieller MADDRAX Fanclub e. V." description="Volltextsuche durch Maddrax-Romane für Mitglieder.">
    <x-member-page class="max-w-6xl space-y-8">
        @php
            $indexedSeriesCount = $indexierteRomaneSummary->count();
        @endphp

        <x-ui.page-header
            eyebrow="Volltextsuche für Mitglieder"
            title="Maddrax-Kompendium"
            description="Die Kompendium-Suche bündelt indexierte Maddrax-Reihen an einer Stelle. Zugriff erhältst du über das Kompendium-Reward oder über deine Mitgliedschaft in der AG Maddraxikon."
        >
            <x-slot:actions>
                <div class="flex flex-wrap gap-2">
                    <span class="badge badge-primary badge-outline rounded-full px-3 py-3">{{ $indexedSeriesCount }} indexierte Reihen</span>
                    <span class="badge badge-outline rounded-full px-3 py-3">{{ $showSearch ? 'Suche aktiv' : 'Suche gesperrt' }}</span>
                    @if($istAdmin ?? false)
                        <span class="badge badge-outline rounded-full px-3 py-3">Admin-Zugang</span>
                    @endif
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        <section class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(18rem,0.74fr)] xl:items-start">
            <div class="space-y-6">
                <x-ui.panel title="Indexierte Reihen" description="Die Übersicht zeigt, welche Romanbereiche derzeit in der Kompendium-Suche enthalten sind.">
                    <x-slot:actions>
                        @if($istAdmin ?? false)
                            <x-button label="Kompendium verwalten" link="{{ route('kompendium.admin') }}" wire:navigate icon="o-cog-6-tooth" class="btn-ghost btn-sm text-primary" />
                        @endif
                    </x-slot:actions>

                    @if($indexierteRomaneSummary->isEmpty())
                        <p class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-4 text-sm leading-relaxed text-base-content/76 sm:text-base">
                            Aktuell sind keine Romane für die Suche indexiert.
                        </p>
                    @else
                        <p class="mb-4 text-sm leading-relaxed text-base-content/68 sm:text-base">
                            Aktuell sind die folgenden Romane für die Suche indexiert:
                        </p>

                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach($indexierteRomaneSummary as $gruppe)
                                <article class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-4">
                                    <p class="text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-base-content/45">Indexiert</p>
                                    <p class="mt-2 text-base text-base-content sm:text-lg"><strong>{{ $gruppe['serie_name'] }}</strong></p>
                                    <p class="mt-2 text-sm leading-relaxed text-base-content/68 sm:text-base">{{ $gruppe['beschreibung'] }}</p>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </x-ui.panel>

                <x-ui.panel title="{{ $showSearch ? 'Volltextsuche' : 'Zugang zum Kompendium' }}" description="{{ $showSearch ? 'Suche direkt in den indexierten Inhalten nach Begriffen, Phrasen und Treffern in verschiedenen Reihen.' : 'Wenn die Suche noch gesperrt ist, kannst du den Zugang direkt über das Reward freischalten oder ihn über die AG Maddraxikon erhalten.' }}">
                    @if($showSearch)
                        @livewire('kompendium-suche')
                    @else
                        @if($kompendiumReward && $kompendiumReward->is_active)
                            <div class="mb-4 rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-4 text-sm leading-relaxed text-base-content/76 sm:text-base">
                                <p>Die Kompendium-Suche ist aktuell noch nicht freigeschaltet.</p>
                                <p class="mt-2">Mit dem Reward schaltest du den Suchzugang für dein Konto frei. Mitglieder der AG Maddraxikon erhalten den Zugriff automatisch.</p>
                            </div>

                            @livewire('kompendium-kauf-overlay', [
                                'rewardId' => $kompendiumReward->id,
                            ])
                        @else
                            <x-alert icon="o-lock-closed" class="alert-warning mb-4">
                                Das Kompendium ist derzeit nicht verfügbar.
                            </x-alert>
                        @endif
                    @endif
                </x-ui.panel>
            </div>

            <div class="space-y-6 xl:sticky xl:top-6">
                <x-ui.panel title="Zugangsmodell" description="Der Zugang zur Suche ist bewusst an Vereinsaktivität gekoppelt und bleibt dadurch übersichtlich steuerbar.">
                    <ul class="grid gap-3 text-sm leading-relaxed text-base-content/76 sm:text-base">
                        <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Mit dem Kompendium-Reward aktivierst du die Suche direkt für dein Konto.</li>
                        <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Mitglieder der AG Maddraxikon können das Werkzeug ohne separaten Kauf nutzen.</li>
                        <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Admins sehen zusätzlich den direkten Einstieg in die Verwaltungsoberfläche.</li>
                    </ul>
                </x-ui.panel>

                <x-ui.panel title="Aktueller Stand" description="So ist das Kompendium in deinem aktuellen Seitenaufruf konfiguriert.">
                    <div class="grid gap-3 text-sm leading-relaxed text-base-content/76 sm:text-base">
                        <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">
                            <p class="font-medium text-base-content">Indexierte Reihen</p>
                            <p class="mt-1">{{ $indexedSeriesCount }} Gruppen stehen aktuell für die Suche bereit.</p>
                        </div>
                        <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">
                            <p class="font-medium text-base-content">Suchzugang</p>
                            <p class="mt-1">{{ $showSearch ? 'Die Volltextsuche ist freigeschaltet.' : 'Die Volltextsuche ist aktuell noch gesperrt.' }}</p>
                        </div>
                        <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">
                            <p class="font-medium text-base-content">Reward-Status</p>
                            <p class="mt-1">{{ ($kompendiumReward && $kompendiumReward->is_active) ? 'Das Kompendium-Reward kann aktuell verwendet werden.' : 'Das Kompendium-Reward ist derzeit nicht aktiv.' }}</p>
                        </div>
                    </div>
                </x-ui.panel>
            </div>
        </section>
    </x-member-page>
</x-app-layout>
