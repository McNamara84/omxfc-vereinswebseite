<x-app-layout title="Kompendium – Offizieller MADDRAX Fanclub e. V." description="Volltextsuche durch Maddrax-Romane für Mitglieder.">
    <x-member-page class="max-w-6xl space-y-8">
        @php
            $indexedSeriesCount = $indexierteRomaneSummary->count();
        @endphp

        <x-ui.page-header
            eyebrow="Volltextsuche für Mitglieder"
            title="Maddrax-Kompendium"
            description="Das Kompendium bündelt indexierte MADDRAX-Serien an einer Stelle. Wenn dein Zugang aktiv ist, startest du direkt hier mit Suche und Filtern."
        >
            <x-slot:actions>
                <div class="flex flex-wrap gap-2">
                    <span class="badge badge-primary badge-outline rounded-full px-3 py-3">{{ $indexedSeriesCount }} indexierte Serien</span>
                    <span class="badge badge-outline rounded-full px-3 py-3">{{ $showSearch ? 'Suche aktiv' : 'Suche gesperrt' }}</span>
                    @if($istAdmin ?? false)
                        <span class="badge badge-outline rounded-full px-3 py-3">Admin-Zugang</span>
                    @endif
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        @if($showSearch)
            <section data-testid="kompendium-primary-search">
                <x-ui.panel title="Volltextsuche" description="Suche direkt in den indexierten Serien. Hilfe zu Syntax und Filtern findest du direkt im Suchbereich.">
                    @livewire('kompendium-suche')
                </x-ui.panel>
            </section>
        @else
            <section data-testid="kompendium-primary-access">
                <x-ui.panel title="Zugang zum Kompendium" description="Die Suche ist für dein Konto noch gesperrt. Die Freischaltung erledigst du direkt hier; die Hintergründe erklärt die Hilfe.">
                    <x-slot:actions>
                        <button
                            type="button"
                            class="btn btn-circle btn-ghost btn-sm"
                            aria-label="Hilfe zum Kompendium-Zugang anzeigen"
                            data-testid="kompendium-access-help-button"
                            onclick="document.getElementById('kompendium-access-help-modal').showModal()"
                        >
                            <x-icon name="o-question-mark-circle" class="h-5 w-5" />
                        </button>
                    </x-slot:actions>

                    @if($kompendiumReward && $kompendiumReward->is_active)
                        <div class="mb-4 rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-4 text-sm leading-relaxed text-base-content/76 sm:text-base">
                            <p>Die Kompendium-Suche ist für dein Konto noch nicht freigeschaltet.</p>
                            <p class="mt-2">Im Bereich Belohnungen einlösen schaltest du den Zugang frei. Mitglieder der AG Maddraxikon erhalten ihn automatisch.</p>
                        </div>

                        @livewire('kompendium-kauf-overlay', [
                            'rewardId' => $kompendiumReward->id,
                        ])
                    @else
                        <x-alert icon="o-lock-closed" class="alert-warning mb-4">
                            Das Kompendium ist derzeit nicht verfügbar.
                        </x-alert>
                    @endif
                </x-ui.panel>
            </section>
        @endif

        <section class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(18rem,0.74fr)] xl:items-start">
            <div class="space-y-6">
                <x-ui.panel title="Indexierte Serien" description="Die Übersicht zeigt, welche Serien derzeit im Kompendium durchsuchbar sind.">
                    <x-slot:actions>
                        <div class="flex flex-wrap items-center gap-2">
                            @if($istAdmin ?? false)
                                <x-button label="Kompendium verwalten" link="{{ route('kompendium.admin') }}" wire:navigate icon="o-cog-6-tooth" class="btn-ghost btn-sm text-primary" />
                            @endif
                            <button
                                type="button"
                                class="btn btn-circle btn-ghost btn-sm"
                                aria-label="Hilfe zu den indexierten Serien anzeigen"
                                data-testid="kompendium-series-help-button"
                                onclick="document.getElementById('kompendium-series-help-modal').showModal()"
                            >
                                <x-icon name="o-question-mark-circle" class="h-5 w-5" />
                            </button>
                        </div>
                    </x-slot:actions>

                    @if($indexierteRomaneSummary->isEmpty())
                        <p class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-4 text-sm leading-relaxed text-base-content/76 sm:text-base">
                            Aktuell sind keine Romane für die Suche indexiert.
                        </p>
                    @else
                        <p class="mb-4 text-sm leading-relaxed text-base-content/68 sm:text-base">
                            Aktuell sind diese Serien für die Suche indexiert:
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
            </div>

            <div class="space-y-6 xl:sticky xl:top-6">
                <x-ui.panel title="Aktueller Stand" description="Der Schnellüberblick zu Zugang, Inhalt und Reward-Status.">
                    <div class="grid gap-3 text-sm leading-relaxed text-base-content/76 sm:text-base">
                        <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">
                            <p class="font-medium text-base-content">Indexierte Serien</p>
                            <p class="mt-1">{{ $indexedSeriesCount }} Serien stehen aktuell für die Suche bereit.</p>
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

        <x-mary-modal id="kompendium-access-help-modal" title="So funktioniert der Zugang" separator without-trap-focus>
            <div class="space-y-3 text-sm leading-relaxed text-base-content/80 sm:text-base">
                <p>Das Kompendium ist ein Volltextwerkzeug für Mitglieder. Der Zugang wird bewusst separat gesteuert, damit nur freigeschaltete Konten suchen können.</p>
                <p>Es gibt zwei Wege zur Freischaltung: über das Kompendium-Reward im Bereich Belohnungen einlösen oder über die Mitgliedschaft in der AG Maddraxikon.</p>
                <p>Admins sehen zusätzlich den direkten Einstieg in die Verwaltungsoberfläche, wenn die Suche freigeschaltet ist.</p>
            </div>

            <x-slot:actions>
                <x-button label="Schließen" @click="document.getElementById('kompendium-access-help-modal').close()" />
            </x-slot:actions>
        </x-mary-modal>

        <x-mary-modal id="kompendium-series-help-modal" title="Was in den indexierten Serien steckt" separator without-trap-focus>
            <div class="space-y-3 text-sm leading-relaxed text-base-content/80 sm:text-base">
                <p>Die Übersicht zeigt, welche Serien aktuell für die Volltextsuche vorbereitet wurden. Sie beschreibt also den durchsuchbaren Bestand, nicht nur verfügbare Titel im allgemeinen Vereinsangebot.</p>
                <p>Die Karten fassen zusammen, welche Bereiche oder Zyklen derzeit bereits indexiert sind. Mit jeder weiteren Indexierung wächst die durchsuchbare Menge.</p>
            </div>

            <x-slot:actions>
                <x-button label="Schließen" @click="document.getElementById('kompendium-series-help-modal').close()" />
            </x-slot:actions>
        </x-mary-modal>
    </x-member-page>
</x-app-layout>
