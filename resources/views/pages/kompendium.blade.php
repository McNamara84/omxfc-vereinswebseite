<x-app-layout title="Kompendium – Offizieller MADDRAX Fanclub e. V." description="Volltextsuche durch Maddrax-Romane für Mitglieder.">
    <div class="pb-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
                {{-- Überschrift ------------------------------------------------ --}}
                <h1 class="text-2xl font-semibold text-[#8B0116] dark:text-[#ff4b63] mb-6">
                    Maddrax-Kompendium
                </h1>

                {{-- Info-Card --------------------------------------------------- --}}
                <div class="mb-6 p-4 border-l-4 border-[#8B0116] dark:border-[#ff4b63] bg-gray-50 dark:bg-gray-700 rounded">
                    @if($indexierteRomaneSummary->isEmpty())
                        <p class="text-gray-600 dark:text-gray-400">
                            Aktuell sind keine Romane für die Suche indexiert.
                        </p>
                    @else
                        <p class="mb-2">Aktuell sind die folgenden Romane für die Suche indexiert:</p>
                        <ul class="list-disc ml-6">
                            @foreach($indexierteRomaneSummary as $gruppe)
                                <li>
                                    <strong>{{ $gruppe['name'] }}</strong>
                                    (Band {{ $gruppe['bandbereich'] }})
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if($istAdmin ?? false)
                        <a href="{{ route('kompendium.admin') }}"
                           class="inline-flex items-center mt-4 text-sm text-[#8B0116] dark:text-[#ff4b63] hover:underline">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            Kompendium verwalten
                        </a>
                    @endif
                </div>

                {{-- Suchschlitz (ab 100 Baxx) -------------------------------- --}}
                @if($showSearch)
                    <div class="mb-4">
                        <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Volltextsuche
                        </label>
                        <input id="search"
                               type="text"
                               placeholder="Suchbegriff eingeben … (Enter)"
                               class="w-full px-4 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-[#8B0116]"
                        >
                    </div>
                @else
                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                        Die Suche wird ab <strong>{{ $required }}</strong> Baxx freigeschaltet.<br>
                        Dein aktueller Stand: <span class="font-semibold">{{ $userPoints }}</span>.
                    </p>
                @endif

                {{-- Trefferliste ---------------------------------------------- --}}
                <div id="results" class="space-y-6"></div>

                {{-- Loader ----------------------------------------------------- --}}
                <div id="loading" class="hidden text-center py-4">
                    <svg class="animate-spin h-6 w-6 mx-auto"
                         xmlns="http://www.w3.org/2000/svg"
                         fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10"
                                stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    {{-- JavaScript nur, wenn Suche erlaubt ---------------------------------- --}}
    @if($userPoints >= 100)
        <script>
            (() => {
                let page   = 1;
                let query  = '';
                let busy   = false;
                const perFetchOffset = 200;                       // px vor Seitenende
                const $search  = document.getElementById('search');
                const $results = document.getElementById('results');
                const $loading = document.getElementById('loading');

                // HTML-Template pro Roman
                const tpl = (roman) => `
                    <div class="border border-gray-200 dark:border-gray-700 rounded p-4">
                        <h2 class="font-semibold text-[#8B0116] dark:text-[#ff4b63] mb-2">
                            ${roman.cycle} – ${roman.romanNr}: ${roman.title}
                        </h2>
                        ${roman.snippets.map(s => `<p class="mb-2 text-sm leading-relaxed">${s}</p>`).join('')}
                    </div>`;


                async function fetchHits() {
                    if (busy) return;
                    busy = true;
                    $loading.classList.remove('hidden');

                    const url = `{{ route('kompendium.search') }}?q=${encodeURIComponent(query)}&page=${page}`;
                    const res = await fetch(url);
                    const json = await res.json();

                    json.data.forEach(r => $results.insertAdjacentHTML('beforeend', tpl(r)));

                    page++;
                    busy = false;
                    $loading.classList.add('hidden');

                    // Ende erreicht? → Scroll-Listener entfernen
                    if (page > json.lastPage) {
                        window.removeEventListener('scroll', onScroll);
                    }
                }

                function onScroll() {
                    if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - perFetchOffset) {
                        fetchHits();
                    }
                }

                // Suche starten
                $search.addEventListener('keyup', e => {
                    if (e.key === 'Enter' && $search.value.trim().length >= 2) {
                        query  = $search.value.trim();
                        page   = 1;
                        $results.innerHTML = '';
                        window.removeEventListener('scroll', onScroll);
                        fetchHits().then(() => window.addEventListener('scroll', onScroll));
                    }
                });
            })();
        </script>
    @endif
</x-app-layout>
