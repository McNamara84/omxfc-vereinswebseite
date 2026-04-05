<div>
    @if($this->reviews->isEmpty())
        <p class="mt-4 text-sm text-base-content/80" role="status" aria-live="polite">
            Derzeit liegen keine Rezensionen vor. Schau später noch einmal vorbei.
        </p>
    @else
        <ul class="mt-4 divide-y divide-base-content/10" aria-label="Neueste Rezensionen">
            @foreach($this->reviews as $review)
                <li class="py-4" wire:key="review-{{ $loop->index }}">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <span class="inline-flex w-fit items-center gap-2 rounded-full bg-[#8B0116]/10 text-[#8B0116] dark:bg-[#ff4b63]/15 dark:text-[#ff4b63] px-3 py-1 text-xs font-semibold"
                              aria-label="Roman Nummer {{ $review['roman_number'] }}">
                            Roman Nr. {{ $review['roman_number'] }}
                        </span>
                        <p class="text-sm text-gray-700 dark:text-gray-200 font-medium">
                            {{ $review['roman_title'] }}
                        </p>
                    </div>
                    <h3 class="mt-3 text-base font-semibold text-gray-900 dark:text-white flex flex-wrap items-center gap-1">
                        <span>{{ $review['review_title'] }}</span>
                        vom
                        <time datetime="{{ $review['reviewed_at']->toISOString() }}"
                              class="text-sm font-normal text-gray-700 dark:text-gray-300"
                              aria-label="Rezension veröffentlicht am {{ $review['reviewed_at']->isoFormat('D. MMM YYYY') }}">
                            {{ $review['reviewed_at']->isoFormat('D. MMM YYYY') }}
                        </time>
                    </h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                        {{ $review['excerpt'] }}
                    </p>
                </li>
            @endforeach
        </ul>
    @endif
</div>
