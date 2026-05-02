<div>
    @if($this->reviews->isEmpty())
        <x-ui.empty-state
            class="mt-4"
            icon="o-book-open"
            title="Noch keine Rezensionen"
            description="Derzeit liegen keine Rezensionen vor. Schau spaeter noch einmal vorbei."
        />
    @else
        <ul class="mt-4 divide-y divide-base-content/10" aria-label="Neueste Rezensionen">
            @foreach($this->reviews as $review)
                <li class="py-4" wire:key="review-{{ $loop->index }}">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <span class="inline-flex w-fit items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary ring-1 ring-primary/10"
                              aria-label="Roman Nummer {{ $review['roman_number'] }}">
                            Roman Nr. {{ $review['roman_number'] }}
                        </span>
                        <p class="text-sm font-medium text-base-content/72">
                            {{ $review['roman_title'] }}
                        </p>
                    </div>
                    <h3 class="mt-3 flex flex-wrap items-center gap-1 text-base font-semibold text-base-content">
                        <span>{{ $review['review_title'] }}</span>
                        vom
                        <time datetime="{{ $review['reviewed_at']->toISOString() }}"
                              class="text-sm font-normal text-base-content/60"
                              aria-label="Rezension veröffentlicht am {{ $review['reviewed_at']->isoFormat('D. MMM YYYY') }}">
                            {{ $review['reviewed_at']->isoFormat('D. MMM YYYY') }}
                        </time>
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-base-content/72">
                        {{ $review['excerpt'] }}
                    </p>
                </li>
            @endforeach
        </ul>
    @endif
</div>
