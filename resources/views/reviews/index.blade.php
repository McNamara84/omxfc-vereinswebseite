<x-app-layout :title="$title" :description="$description">
    <x-member-page>
            <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                <h1 class="text-2xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-2">Rezensionen</h1>
                <p class="text-base text-gray-700 dark:text-gray-300">
                    Für jede <strong>zehnte</strong> verfasste Rezension erhältst du automatisch
                    <strong>1 Baxx</strong>.
                </p>
            </div>
            @php
                $filtersApplied = request()->filled('roman_number') || request()->filled('title') || request()->filled('author') || request()->filled('review_status');
            @endphp
            <div x-data="{ open: @js($filtersApplied) }" class="mb-6">
                <button
                    type="button"
                    @click="open = !open"
                    class="w-full flex justify-between items-center bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-4"
                    :aria-expanded="open"
                    aria-controls="reviews-filter-panel"
                >
                    <span class="font-semibold text-gray-700 dark:text-gray-300" x-text="open ? 'Filter ausblenden' : 'Filter anzeigen'"></span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 transform transition-transform" :class="{ 'rotate-180': open }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.25 8.27a.75.75 0 01-.02-1.06z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div
                    id="reviews-filter-panel"
                    x-show="open"
                    x-transition
                    class="mt-4"
                    x-bind:aria-hidden="!open"
                >
                    <form method="GET" action="{{ route('reviews.index') }}" class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <x-form name="roman_number" label="Nr.">
                                <input id="roman_number" name="roman_number" aria-describedby="roman_number-error" type="text" value="{{ request('roman_number') }}" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded" />
                            </x-form>
                            <x-form name="title" label="Titel">
                                <input id="title" name="title" aria-describedby="title-error" type="text" value="{{ request('title') }}" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded" />
                            </x-form>
                            <x-form name="author" label="Autor">
                                <input id="author" name="author" aria-describedby="author-error" type="text" value="{{ request('author') }}" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded" />
                            </x-form>
                            <x-form name="review_status" label="Rezensionsstatus">
                                <select id="review_status" name="review_status" aria-describedby="review_status-error" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded">
                                    <option value="">Alle</option>
                                    <option value="with" @selected(request('review_status') === 'with')>Mit Rezension</option>
                                    <option value="without" @selected(request('review_status') === 'without')>Ohne Rezension</option>
                                </select>
                            </x-form>
                        </div>
                        <div class="mt-4 flex flex-col sm:flex-row sm:items-center gap-2">
                            <button type="submit" class="bg-[#8B0116] dark:bg-[#FCA5A5] text-white px-4 py-2 rounded">Filtern</button>
                            <a href="{{ route('reviews.index') }}" class="text-gray-600 dark:text-gray-400 hover:underline">Zurücksetzen</a>
                        </div>
                    </form>
                </div>
            </div>
            <div id="accordion">
                @foreach($booksByCycle as $cycle => $cycleBooks)
                    @php
                        $id = \Illuminate\Support\Str::slug($cycle);
                        $reviewCount = $cycleBooks->sum('reviews_count');
                    @endphp
                    <div
                        class="mb-4 border border-gray-200 dark:border-gray-700 rounded-lg"
                        data-reviews-accordion
                        @if($loop->first) data-reviews-accordion-open="true" @endif
                    >
                        <h2>
                            <button
                                type="button"
                                class="w-full flex justify-between items-center bg-gray-100 dark:bg-gray-700 px-4 py-3 rounded-t-lg font-semibold"
                                data-reviews-accordion-button
                                aria-controls="content-{{ $id }}"
                                aria-expanded="{{ $loop->first ? 'true' : 'false' }}"
                            >
                                <span>{{ $cycle }}-Zyklus ({{ $reviewCount }} {{ $reviewCount === 1 ? 'Rezension' : 'Rezensionen' }})</span>
                                <span aria-hidden="true" data-reviews-accordion-icon>{{ $loop->first ? '−' : '+' }}</span>
                            </button>
                        </h2>
                        <div
                            id="content-{{ $id }}"
                            data-reviews-accordion-panel
                            class="{{ $loop->first ? '' : 'hidden' }} bg-white dark:bg-gray-900 px-4 py-2 rounded-b-lg"
                            @if(!$loop->first) hidden @endif
                            aria-hidden="{{ $loop->first ? 'false' : 'true' }}"
                        >
                            <x-book-list :books="$cycleBooks->sortByDesc('roman_number')" />
                        </div>
                    </div>
                    @if($cycle === 'Wandler' && $missionMars->isNotEmpty())
                        @php
                            $id = 'mission-mars';
                            $reviewCount = $missionMars->sum('reviews_count');
                        @endphp
                        <div class="mb-4 border border-gray-200 dark:border-gray-700 rounded-lg" data-reviews-accordion>
                            <h2>
                                <button
                                    type="button"
                                    class="w-full flex justify-between items-center bg-gray-100 dark:bg-gray-700 px-4 py-3 rounded-t-lg font-semibold"
                                    data-reviews-accordion-button
                                    aria-controls="content-{{ $id }}"
                                    aria-expanded="false"
                                >
                                    <span>Mission Mars-Heftromane ({{ $reviewCount }} {{ $reviewCount === 1 ? 'Rezension' : 'Rezensionen' }})</span>
                                    <span aria-hidden="true" data-reviews-accordion-icon>+</span>
                                </button>
                            </h2>
                            <div
                                id="content-{{ $id }}"
                                data-reviews-accordion-panel
                                class="hidden bg-white dark:bg-gray-900 px-4 py-2 rounded-b-lg"
                                hidden
                                aria-hidden="true"
                            >
                                <x-book-list :books="$missionMars" />
                            </div>
                        </div>
                    @endif
                @endforeach
                @if($hardcovers->isNotEmpty())
                    @php
                        $id = 'maddrax-hardcover';
                        $reviewCount = $hardcovers->sum('reviews_count');
                    @endphp
                    <div class="mb-4 border border-gray-200 dark:border-gray-700 rounded-lg" data-reviews-accordion>
                        <h2>
                            <button
                                type="button"
                                class="w-full flex justify-between items-center bg-gray-100 dark:bg-gray-700 px-4 py-3 rounded-t-lg font-semibold"
                                data-reviews-accordion-button
                                aria-controls="content-{{ $id }}"
                                aria-expanded="false"
                            >
                                <span>Maddrax-Hardcover ({{ $reviewCount }} {{ $reviewCount === 1 ? 'Rezension' : 'Rezensionen' }})</span>
                                <span aria-hidden="true" data-reviews-accordion-icon>+</span>
                            </button>
                        </h2>
                        <div
                            id="content-{{ $id }}"
                            data-reviews-accordion-panel
                            class="hidden bg-white dark:bg-gray-900 px-4 py-2 rounded-b-lg"
                            hidden
                            aria-hidden="true"
                        >
                            <x-book-list :books="$hardcovers" />
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
    </x-member-page>
</x-app-layout>