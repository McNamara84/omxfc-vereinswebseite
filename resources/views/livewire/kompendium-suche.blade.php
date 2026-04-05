<div>
    <div class="mb-4">
        <x-input
            wire:model="query"
            wire:keydown.enter="performSearch"
            placeholder="Suchbegriff eingeben … (Enter)"
            icon="o-magnifying-glass"
            data-testid="kompendium-search"
        />
    </div>

    {{-- Serien-Filter --}}
    @if(count($this->verfuegbareSerien) >= 2)
        <div class="mb-4" id="serien-filter">
            <fieldset role="group" aria-labelledby="serien-filter-legend">
                <legend id="serien-filter-legend" class="text-sm font-medium text-base-content mb-2">
                    Serien filtern:
                </legend>
                <div id="serien-checkboxes" class="flex flex-wrap gap-x-4 gap-y-2" role="group">
                    @foreach($this->verfuegbareSerien as $key => $name)
                        <label class="inline-flex items-center text-sm text-base-content cursor-pointer">
                            <input
                                type="checkbox"
                                wire:model.live="selectedSerien"
                                value="{{ $key }}"
                                class="checkbox checkbox-primary checkbox-sm mr-1.5"
                                aria-describedby="serien-filter-legend"
                            />
                            <span>{{ $name }}{{ isset($serienCounts[$key]) ? ' (' . $serienCounts[$key] . ')' : '' }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        </div>
    @endif

    {{-- Phrasensuche-Hinweis --}}
    @if($isPhraseSearch && $hasSearched)
        <div class="mb-4" data-testid="phrase-hint">
            <div class="flex items-center gap-2 p-3 text-sm bg-info/10 border border-info/30 rounded">
                <x-icon name="o-information-circle" class="w-5 h-5 text-info shrink-0" />
                <span>
                    Phrasensuche aktiv: Nur exakte Treffer für
                    @foreach($searchInfo['phrases'] ?? [] as $phrase)
                        „{{ $phrase }}“@if(!$loop->last) + @endif
                    @endforeach
                    @if(!empty($searchInfo['terms']))
                        + {{ implode(' + ', $searchInfo['terms']) }}
                    @endif
                </span>
            </div>
        </div>
    @endif

    {{-- Trefferliste --}}
    <div id="results" class="space-y-6">
        @foreach($results as $hit)
            <div class="border border-base-content/10 rounded p-4" wire:key="hit-{{ $hit['serie'] }}-{{ $hit['romanNr'] }}">
                <h2 class="font-semibold text-primary mb-2">
                    {{ $hit['cycle'] }} – {{ $hit['romanNr'] }}: {{ $hit['title'] }}
                </h2>
                {{-- Snippet-HTML ist sicher: Segmente werden mit e() escaped,
                     nur <mark>-Tags werden für Treffer-Highlighting eingefügt. --}}
                @foreach($hit['snippets'] as $snippet)
                    <p class="mb-2 text-sm leading-relaxed">{!! $snippet !!}</p>
                @endforeach
            </div>
        @endforeach
    </div>

    @if($hasSearched && empty($results) && !$error)
        <p class="text-center text-base-content/60 py-4">Keine Treffer gefunden.</p>
    @endif

    @if($error)
        <div class="p-4 border-l-4 border-error bg-error/10 rounded">
            <p class="text-error">{{ $error }}</p>
        </div>
    @endif

    @if($page < $lastPage)
        <div class="text-center py-4">
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

    <div wire:loading wire:target="performSearch" class="text-center py-4">
        <x-loading class="loading-spinner loading-md" />
    </div>
</div>
