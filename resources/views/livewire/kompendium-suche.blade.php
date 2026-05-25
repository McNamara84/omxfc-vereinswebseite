<div class="space-y-6">
    @php
        $positivePhrases = $searchInfo['phrases'] ?? [];
        $positiveTerms = $searchInfo['terms'] ?? [];
        $excludedPhrases = $searchInfo['excludedPhrases'] ?? [];
        $excludedTerms = $searchInfo['excludedTerms'] ?? [];
        $usesOrOperator = (bool) ($searchInfo['usesOrOperator'] ?? false);
        $usesNotOperator = (bool) ($searchInfo['usesNotOperator'] ?? false);
    @endphp

    <section class="rounded-[1.5rem] border border-base-content/10 bg-base-100/80 px-4 py-4 shadow-sm sm:px-6 sm:py-5" data-testid="kompendium-search-shell">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="space-y-2">
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-semibold text-base-content">Suchbegriff</h2>
                    <button
                        type="button"
                        class="btn btn-circle btn-ghost btn-sm"
                        aria-label="Hilfe zur Suchsyntax anzeigen"
                        data-testid="kompendium-search-help-button"
                        onclick="document.getElementById('kompendium-search-help-modal').showModal()"
                    >
                        <x-icon name="o-question-mark-circle" class="h-5 w-5" />
                    </button>
                </div>
                <p class="text-sm leading-relaxed text-base-content/70 sm:text-base">
                    Leerzeichen verknüpfen Begriffe mit UND. Für Alternativen nutze OR, für Ausschlüsse NOT oder ein führendes Minus.
                </p>
            </div>

            <span class="badge badge-outline rounded-full px-3 py-3 text-[0.72rem] font-semibold uppercase tracking-[0.2em]">Volltextsuche</span>
        </div>

        <div class="mt-4 grid gap-3 lg:grid-cols-[minmax(0,1fr)_auto]">
            <x-input
                wire:model="query"
                wire:keydown.enter="performSearch"
                placeholder="Begriff, Phrase oder Operator eingeben …"
                icon="o-magnifying-glass"
                data-testid="kompendium-search"
            />

            <x-button
                wire:click="performSearch"
                label="Suchen"
                icon="o-magnifying-glass"
                class="btn-primary w-full lg:w-auto"
                wire:loading.attr="disabled"
                wire:target="performSearch"
            />
        </div>
    </section>

    @if(count($this->verfuegbareSerien) >= 2)
        <section class="rounded-[1.5rem] border border-base-content/10 bg-base-100/72 px-4 py-4 sm:px-6 sm:py-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-base-content/55">Serien filtern</h3>
                        <button
                            type="button"
                            class="btn btn-circle btn-ghost btn-sm"
                            aria-label="Hilfe zu den Serienfiltern anzeigen"
                            data-testid="kompendium-filter-help-button"
                            onclick="document.getElementById('kompendium-filter-help-modal').showModal()"
                        >
                            <x-icon name="o-question-mark-circle" class="h-5 w-5" />
                        </button>
                    </div>
                    <p class="mt-2 text-sm leading-relaxed text-base-content/70 sm:text-base">
                        Wähle die Serien, in denen Treffer angezeigt werden sollen. Wenn keine Serie aktiv bleibt, werden automatisch wieder alle Serien ausgewählt.
                    </p>
                </div>
            </div>

            <div class="mt-4" id="serien-filter">
                <fieldset role="group" aria-labelledby="serien-filter-legend">
                    <legend id="serien-filter-legend" class="sr-only">Serien filtern</legend>
                    <div id="serien-checkboxes" class="flex flex-wrap gap-2" role="group">
                        @foreach($this->verfuegbareSerien as $key => $name)
                            <label class="cursor-pointer">
                                <input
                                    type="checkbox"
                                    wire:model.live="selectedSerien"
                                    value="{{ $key }}"
                                    class="peer sr-only"
                                    aria-describedby="serien-filter-legend"
                                />
                                <span class="inline-flex items-center gap-2 rounded-full border border-base-content/12 bg-base-100 px-3 py-2 text-sm text-base-content/78 transition peer-checked:border-primary peer-checked:bg-primary peer-checked:text-primary-content">
                                    <span>{{ $name }}</span>
                                    @if(isset($serienCounts[$key]))
                                        <span class="rounded-full bg-base-content/10 px-2 py-0.5 text-[0.68rem] font-semibold peer-checked:bg-primary-content/20 peer-checked:text-primary-content">
                                            {{ $serienCounts[$key] }}
                                        </span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            </div>
        </section>
    @endif

    @if($hasSearched && (!empty($searchInfo) || $error))
        <section class="rounded-[1.5rem] border border-info/20 bg-info/8 px-4 py-4 sm:px-6 sm:py-5" data-testid="kompendium-search-summary">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-base-content/55">Aktive Suchlogik</h3>
                        <button
                            type="button"
                            class="btn btn-circle btn-ghost btn-sm"
                            aria-label="Suchbeispiele anzeigen"
                            data-testid="kompendium-search-examples-button"
                            onclick="document.getElementById('kompendium-search-help-modal').showModal()"
                        >
                            <x-icon name="o-question-mark-circle" class="h-5 w-5" />
                        </button>
                    </div>
                    <p class="mt-2 text-sm leading-relaxed text-base-content/76 sm:text-base">
                        Leerzeichen bedeuten UND, Anführungszeichen markieren exakte Phrasen, OR verbindet Alternativen und NOT oder ein führendes Minus schließt Begriffe aus.
                    </p>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                @foreach($positivePhrases as $phrase)
                    <span class="badge badge-outline px-3 py-3">Phrase: „{{ $phrase }}“</span>
                @endforeach

                @foreach($positiveTerms as $term)
                    <span class="badge badge-outline px-3 py-3">Begriff: {{ $term }}</span>
                @endforeach

                @foreach($excludedPhrases as $phrase)
                    <span class="badge badge-outline badge-error px-3 py-3">Ohne Phrase: „{{ $phrase }}“</span>
                @endforeach

                @foreach($excludedTerms as $term)
                    <span class="badge badge-outline badge-error px-3 py-3">Ohne: {{ $term }}</span>
                @endforeach

                @if($usesOrOperator)
                    <span class="badge badge-outline badge-info px-3 py-3">OR aktiv</span>
                @endif

                @if($usesNotOperator)
                    <span class="badge badge-outline badge-warning px-3 py-3">NOT aktiv</span>
                @endif
            </div>
        </section>
    @endif

    @if($error)
        <div class="rounded-[1.25rem] border-l-4 border-error bg-error/10 p-4">
            <p class="text-error">{{ $error }}</p>
        </div>
    @endif

    @if($hasSearched && $candidatesTruncated && !$error)
        <div class="rounded-[1.25rem] border-l-4 border-warning bg-warning/10 p-4" data-testid="kompendium-candidate-limit-hint">
            <p class="text-sm leading-relaxed text-base-content/80 sm:text-base">
                Für die Suchlogik wurden bisher {{ $scannedCandidates }} Kandidaten nachgeprüft. Weitere passende Treffer sind möglich. Verfeinere die Suche oder lade weitere Treffer nach, um tiefer in der Trefferliste zu prüfen.
            </p>
        </div>
    @endif

    <section id="results" class="space-y-6">
        @if(!empty($results))
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-base-content">Treffer</h2>
                    <p class="text-sm leading-relaxed text-base-content/68 sm:text-base">
                        {{ count($results) }} Treffer bisher geladen{{ $lastPage > 1 ? ', aktuell Seite ' . $page . ' von ' . ($candidatesTruncated ? 'mindestens ' : '') . $lastPage : '' }}.
                    </p>
                </div>
            </div>
        @endif

        @foreach($results as $hit)
            <article class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 p-4" wire:key="hit-{{ $hit['serie'] }}-{{ $hit['romanNr'] }}">
                <h3 class="mb-2 font-semibold text-primary">
                    {{ $hit['cycle'] }} – {{ $hit['romanNr'] }}: {{ $hit['title'] }}
                </h3>
                {{-- Snippet-HTML ist sicher: Segmente werden mit e() escaped,
                     nur <mark>-Tags werden für Treffer-Highlighting eingefügt. --}}
                @foreach($hit['snippets'] as $snippet)
                    <p class="mb-2 text-sm leading-relaxed">{!! $snippet !!}</p>
                @endforeach
            </article>
        @endforeach
    </section>

    @if($hasSearched && empty($results) && !$error)
        <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-6 text-center text-base-content/60">
            Keine Treffer gefunden.
        </div>
    @endif

    @if($page < $lastPage)
        <div class="py-2 text-center">
            <x-button
                wire:click="loadMore"
                label="Weitere Treffer laden"
                icon="o-arrow-down"
                class="btn-ghost"
                wire:loading.attr="disabled"
                wire:target="loadMore"
            />
            <div wire:loading wire:target="loadMore" class="mt-2">
                <x-loading class="loading-spinner loading-md" />
            </div>
        </div>
    @endif

    <div wire:loading wire:target="performSearch" class="py-4 text-center">
        <x-loading class="loading-spinner loading-md" />
    </div>

    <x-mary-modal id="kompendium-search-help-modal" title="So funktioniert die Suche" separator without-trap-focus>
        <div class="space-y-3 text-sm leading-relaxed text-base-content/80 sm:text-base">
            <p>Die Suche arbeitet im Volltext der indexierten Romane und ist nicht auf Titel oder Seriennamen beschränkt.</p>
            <ul class="list-disc space-y-2 pl-5">
                <li>Leerzeichen bedeuten UND: matthew drax findet nur Treffer, in denen beide Begriffe vorkommen.</li>
                <li>Anführungszeichen suchen exakt: "matthew drax" findet nur diese Wortfolge.</li>
                <li>OR verbindet Alternativen: matthew OR aruula findet Treffer mit einem der beiden Begriffe.</li>
                <li>NOT oder ein führendes Minus schließt aus: matthew NOT aruula oder matthew -aruula.</li>
                <li>Zwei Wörter innerhalb einer Phrase bleiben zusammen: "matthew drax" ist eine exakte Phrase und kein UND aus zwei Einzelbegriffen.</li>
            </ul>
        </div>

        <x-slot:actions>
            <x-button label="Schließen" onclick="document.getElementById('kompendium-search-help-modal').close()" />
        </x-slot:actions>
    </x-mary-modal>

    <x-mary-modal id="kompendium-filter-help-modal" title="So funktionieren die Serienfilter" separator without-trap-focus>
        <div class="space-y-3 text-sm leading-relaxed text-base-content/80 sm:text-base">
            <p>Die Filter begrenzen nur die angezeigten Treffer, nicht die eigentliche Suchanfrage. Du kannst also erst breit suchen und dann die Ergebnisliste auf einzelne Serien eingrenzen.</p>
            <p>Die kleinen Zahlen an den Serien zeigen, wie viele Treffer aus der aktuellen Anfrage in der jeweiligen Serie liegen.</p>
            <p>Wenn du alle Häkchen entfernst, werden automatisch wieder alle Serien aktiviert, damit die Suche nicht versehentlich ohne sichtbare Treffer endet.</p>
        </div>

        <x-slot:actions>
            <x-button label="Schließen" onclick="document.getElementById('kompendium-filter-help-modal').close()" />
        </x-slot:actions>
    </x-mary-modal>
</div>
