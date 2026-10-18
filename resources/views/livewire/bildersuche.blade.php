<x-member-page class="max-w-7xl space-y-6">
    <x-ui.page-header eyebrow="Cover entdecken" title="Maddrax-Bildersuche"
        description="Finde Cover anhand der Motive und Figuren, die im Maddraxikon verschlagwortet wurden." />

    @if(! $hasAccess)
        <x-ui.panel title="Zugang zur Bildersuche" data-testid="bildersuche-access">
            <p>Mitglieder der AG Maddraxikon erhalten automatisch Zugang. Alle anderen Vereinsmitglieder können die Bildersuche einmalig für {{ $reward?->cost_baxx ?? 100 }} Baxx freischalten.</p>
            @if($reward?->is_active)
                <p class="mt-3">Dein verfügbares Guthaben: {{ $wallet['availableBaxx'] ?? 'wird geprüft' }}{{ is_int($wallet['availableBaxx']) ? ' Baxx' : '' }}.</p>
                @if($wallet['warning'])
                    <p class="mt-2" role="status">{{ $wallet['warning'] }}</p>
                @endif
                <x-button label="Für {{ $reward->cost_baxx }} Baxx freischalten" wire:click="purchase" spinner="purchase"
                    wire:confirm="Möchtest du die Bildersuche einmalig für {{ $reward->cost_baxx }} Baxx freischalten?"
                    :disabled="!is_int($wallet['availableBaxx']) || $wallet['availableBaxx'] < $reward->cost_baxx"
                    class="btn-primary mt-4" data-testid="bildersuche-purchase" />
                @error('reward') <p class="mt-2 text-error" role="alert">{{ $message }}</p> @enderror
            @else
                <p class="mt-3" role="status">Die Freischaltung ist derzeit nicht verfügbar.</p>
            @endif
        </x-ui.panel>
    @else
        <x-ui.panel title="Cover suchen">
            <form wire:submit="performSearch" class="space-y-4" data-testid="bildersuche-form">
                <x-input label="Suchbegriffe" wire:model="query" maxlength="200" placeholder="Zum Beispiel: Aruula Schwert"
                    hint="Alle Begriffe müssen in den Schlagworten eines Covers vorkommen. Auch Wortteile wie Drax werden gefunden."
                    data-testid="bildersuche-query" />
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                    <div class="grow"><x-select label="Serie" wire:model.live="series" :options="$seriesOptions" placeholder="" /></div>
                    <x-button type="submit" label="Suchen" icon="o-magnifying-glass" spinner="performSearch" class="btn-primary" />
                </div>
            </form>
        </x-ui.panel>

        <p class="text-sm">Die Verschlagwortung im Maddraxikon wächst. Du findest hier Cover, für die bereits Schlagworte vorliegen.
            <span class="font-semibold">{{ $searchableCount }} durchsuchbare Cover.</span></p>

        <div aria-live="polite" aria-atomic="true" role="status" data-testid="bildersuche-status">
            @if($results)
                <p>{{ $results->total() }} Cover gefunden.</p>
                @if($results->isEmpty())
                    <p class="mt-2">Keine Treffer. Versuche einen kürzeren Begriff oder eine andere Serie. Die Schlagworte werden laufend ergänzt.</p>
                @endif
            @elseif($searchableCount === 0)
                <p>Es sind noch keine Cover mit Schlagworten verfügbar. Nach der nächsten erfolgreichen Synchronisierung kannst du hier suchen.</p>
            @else
                <p>Gib einen Suchbegriff ein, um passende Cover zu entdecken.</p>
            @endif
        </div>

        @if($results && $results->isNotEmpty())
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4 xl:grid-cols-6" data-testid="bildersuche-results" wire:loading.class="opacity-50">
                @foreach($results as $cover)
                    <article wire:key="bildersuche-cover-{{ $cover->id }}" class="rounded-xl border border-base-content/15 bg-base-100 p-3">
                        <button type="button" wire:click="showCover({{ $cover->id }})" class="block w-full rounded-lg text-left focus-visible:outline-2 focus-visible:outline-primary"
                            aria-label="Cover vergrößern: {{ $cover->book->type->label() }} {{ $cover->book->roman_number }} – {{ $cover->book->title }}"
                            data-testid="bildersuche-cover">
                            <span class="block aspect-[3/4]" x-data="{ failed: false }">
                                <img src="{{ route('bildersuche.cover', [$cover, 'small']) }}" alt="Cover: {{ $cover->book->title }}"
                                    class="h-full w-full object-contain" loading="lazy" x-show="!failed" x-on:error="failed = true" />
                                <span x-show="failed" x-cloak class="block p-2">Cover derzeit nicht verfügbar</span>
                            </span>
                            <span class="mt-3 block text-sm">{{ $cover->book->type->label() }} · Nr. {{ $cover->book->roman_number }}</span>
                            <span class="mt-1 block font-semibold">{{ $cover->book->title }}</span>
                        </button>
                    </article>
                @endforeach
            </div>
            {{ $results->links() }}
        @endif

        @if($preview)
            <dialog wire:key="bildersuche-preview-{{ $preview->id }}" x-data x-init="$el.showModal()"
                @close="$wire.closePreview()" aria-labelledby="bildersuche-preview-title"
                class="modal" data-testid="bildersuche-preview">
                <div class="modal-box max-w-4xl">
                    <div class="flex items-start justify-between gap-3">
                        <h2 id="bildersuche-preview-title" class="text-xl font-semibold">{{ $preview->book->title }}</h2>
                        <button type="button" class="btn btn-sm" @click="$el.closest('dialog').close()" autofocus>Schließen</button>
                    </div>
                    <p class="mt-2">{{ $preview->book->type->label() }} · Nr. {{ $preview->book->roman_number }}</p>
                    <div class="my-4" x-data="{ failed: false }">
                        <img src="{{ route('bildersuche.cover', [$preview, 'large']) }}" alt="Cover: {{ $preview->book->title }}"
                            class="mx-auto max-h-[65vh] w-full object-contain" x-show="!failed" x-on:error="failed = true" />
                        <p x-show="failed" x-cloak role="status">Cover derzeit nicht verfügbar. Bitte versuche es später erneut.</p>
                    </div>
                    <h3 class="font-semibold">Schlagworte</h3>
                    <ul class="my-3 flex flex-wrap gap-2" aria-label="Schlagworte des Covers">
                        @foreach($preview->keywords as $keyword)
                            <li class="rounded-full bg-base-200 px-3 py-1">{{ $keyword->keyword }}</li>
                        @endforeach
                    </ul>
                    @if($preview->source_artist)<p class="text-sm">Urheber: {{ $preview->source_artist }}</p>@endif
                    @if($preview->source_credit)<p class="text-sm">{{ $preview->source_credit }}</p>@endif
                    @if($preview->source_license)<p class="text-sm">{{ $preview->source_license }}</p>@endif
                    <a href="{{ $sourceUrl }}" target="_blank" rel="noopener noreferrer" class="link mt-3 inline-block" data-testid="bildersuche-source">Dateiseite im Maddraxikon (neuer Tab)</a>
                </div>
            </dialog>
        @endif
    @endif
</x-member-page>
