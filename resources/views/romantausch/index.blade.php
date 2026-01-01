<x-app-layout>
    <x-member-page>
        @if(session('success'))
            <div class="mb-4 p-4 bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-600 text-green-800 dark:text-green-200 rounded">
                {{ session('success') }}
            </div>
        @endif
        <!-- Kopfzeile -->
        <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <h1 class="text-2xl font-semibold text-[#8B0116] dark:text-[#FF6B81]">Romantauschbörse</h1>
            </div>
            <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                Für jedes <strong>zehnte</strong> eingestellte Angebot erhältst du automatisch
                <strong>1 Bakk</strong>. Bestätigen beide Parteien einen Tausch, bekommt ihr
                jeweils <strong>2 Baxx</strong> zusätzlich gutgeschrieben.
            </p>
        </div>
        <section class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6" aria-labelledby="swap-process-heading">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 id="swap-process-heading" class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81]">
                        {{ $romantauschInfo['title'] }}
                    </h2>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 max-w-3xl">
                        {{ $romantauschInfo['intro'] }}
                    </p>
                </div>
            </div>
            <ol class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4" aria-label="{{ $romantauschInfo['steps_aria_label'] }}">
                <li class="flex h-full flex-col gap-3 rounded-lg border border-gray-200 bg-white/60 p-4 dark:border-gray-700 dark:bg-gray-800/60">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-[#8B0116] text-sm font-semibold text-white dark:bg-[#FF6B81]">1</span>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ $romantauschInfo['steps']['offer']['title'] }}
                        </h3>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        {{ $romantauschInfo['steps']['offer']['description'] }}
                    </p>
                    <div class="mt-auto">
                        <a href="{{ route('romantausch.create-offer') }}"
                           class="inline-flex items-center justify-center rounded-md bg-[#8B0116] px-4 py-2 text-sm font-semibold text-white transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#8B0116] hover:bg-[#A50019] dark:bg-[#C41E3A] dark:hover:bg-[#D63A4D] dark:focus-visible:ring-[#FF6B81]"
                           aria-label="{{ $romantauschInfo['steps']['offer']['cta_aria'] }}">
                            {{ $romantauschInfo['steps']['offer']['cta'] }}
                        </a>
                    </div>
                </li>
                <li class="flex h-full flex-col gap-3 rounded-lg border border-gray-200 bg-white/60 p-4 dark:border-gray-700 dark:bg-gray-800/60">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-[#8B0116] text-sm font-semibold text-white dark:bg-[#FF6B81]">2</span>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ $romantauschInfo['steps']['request']['title'] }}
                        </h3>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        {{ $romantauschInfo['steps']['request']['description'] }}
                    </p>
                    <div class="mt-auto">
                        <a href="{{ route('romantausch.create-request') }}"
                           class="inline-flex items-center justify-center rounded-md bg-gray-700 px-4 py-2 text-sm font-semibold text-white transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-gray-700 hover:bg-gray-800 dark:bg-gray-500 dark:hover:bg-gray-400 dark:focus-visible:ring-[#FF6B81]"
                           aria-label="{{ $romantauschInfo['steps']['request']['cta_aria'] }}">
                            {{ $romantauschInfo['steps']['request']['cta'] }}
                        </a>
                    </div>
                </li>
                <li class="flex h-full flex-col gap-3 rounded-lg border border-gray-200 bg-white/60 p-4 dark:border-gray-700 dark:bg-gray-800/60">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-[#8B0116] text-sm font-semibold text-white dark:bg-[#FF6B81]">3</span>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ $romantauschInfo['steps']['match']['title'] }}
                        </h3>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        {{ $romantauschInfo['steps']['match']['description'] }}
                    </p>
                </li>
                <li class="flex h-full flex-col gap-3 rounded-lg border border-gray-200 bg-white/60 p-4 dark:border-gray-700 dark:bg-gray-800/60">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-[#8B0116] text-sm font-semibold text-white dark:bg-[#FF6B81]">4</span>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ $romantauschInfo['steps']['confirm']['title'] }}
                        </h3>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        {{ $romantauschInfo['steps']['confirm']['description'] }}
                    </p>
                </li>
            </ol>
        </section>
        @if($activeSwaps->isNotEmpty())
            <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-2">Deine Matches</h2>
                <p class="mb-4 text-gray-600 dark:text-gray-400">Kontaktiert euch gegenseitig über die angezeigten Mailadressen und klickt anschließend auf „Tausch abgeschlossen“. Für jeden abgeschlossenen Tausch gibt es <strong>2 Baxx</strong>!</p>
                <ul class="space-y-4">
                    @foreach($activeSwaps as $swap)
                        <li class="bg-gray-100 dark:bg-gray-700 p-3 rounded">
                            <div class="font-semibold mb-1">
                                <a href="{{ route('romantausch.show-offer', $swap->offer) }}" class="text-[#8B0116] hover:underline">{{ $swap->offer->series }} {{ $swap->offer->book_number }} - {{ $swap->offer->book_title }}</a>
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-300 mb-2">
                                <a href="{{ route('profile.view', $swap->offer->user->id) }}" class="text-[#8B0116] hover:underline">{{ $swap->offer->user->name }}</a> ({{ $swap->offer->user->email }}) ↔ <a href="{{ route('profile.view', $swap->request->user->id) }}" class="text-[#8B0116] hover:underline">{{ $swap->request->user->name }}</a> ({{ $swap->request->user->email }})
                            </div>
                            @php $user = auth()->user(); @endphp
                            @if(($user->is($swap->offer->user) && !$swap->offer_confirmed) || ($user->is($swap->request->user) && !$swap->request_confirmed))
                                <form method="POST" action="{{ route('romantausch.confirm-swap', $swap) }}">
                                    @csrf
                                    <button class="px-4 py-2 bg-[#8B0116] dark:bg-[#C41E3A] text-white rounded">Tausch abgeschlossen</button>
                                </form>
                            @else
                                <p class="text-green-700 dark:text-green-300">Bestätigt.</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Stapel-Angebote --}}
        @if(isset($bundles) && $bundles->isNotEmpty())
            <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
                    <div>
                        <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81]">Stapel-Angebote</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Sammlungen mit mehreren Romanen</p>
                    </div>
                    <a href="{{ route('romantausch.create-bundle-offer') }}"
                       class="inline-flex items-center px-4 py-2 bg-gray-700 dark:bg-gray-600 border border-transparent rounded-md font-semibold text-white hover:bg-gray-800 dark:hover:bg-gray-500">
                        Stapel erstellen
                    </a>
                </div>

                <ul class="space-y-4">
                    @foreach($bundles as $bundle)
                        {{--
                            data-bundle-id: UUID für JS-Funktionen und E2E-Tests.
                            
                            SICHERHEITSHINWEIS zur UUID-Exposition:
                            Die bundle_id (UUID) ist im HTML sichtbar. Das ist akzeptabel weil:
                            1. UUIDv4 sind nicht erratbar (122 Bits Entropie)
                            2. Alle Bundle-Aktionen (Bearbeiten/Löschen) erfordern Authentifizierung
                            3. Die Policy prüft ob der User Besitzer des Bundles ist
                            
                            Falls höhere Sicherheit gewünscht ist, könnte stattdessen ein
                            signierter Token oder Index verwendet werden.
                        --}}
                        <li class="bg-gray-100 dark:bg-gray-700 rounded-lg overflow-hidden" data-bundle-id="{{ $bundle->bundle_id }}" data-book-numbers-display="{{ $bundle->book_numbers_display }}" x-data="{ expanded: false }">
                            {{-- Zusammengeklappte Ansicht --}}
                            <div class="p-4">
                                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $bundle->series }}</span>
                                            <span class="text-sm text-gray-500 dark:text-gray-400">({{ $bundle->total_count }} Romane)</span>
                                        </div>
                                        <p class="text-sm text-gray-600 dark:text-gray-300 mt-1 truncate" title="{{ $bundle->book_numbers_display }}">
                                            Nummern: {{ Str::limit($bundle->book_numbers_display, 50) }}
                                        </p>
                                        <div class="text-sm text-gray-600 dark:text-gray-300 mt-1">
                                            von <a href="{{ route('profile.view', $bundle->user->id) }}" class="text-[#8B0116] hover:underline dark:text-[#FF6B81]">{{ $bundle->user->name }}</a>
                                            • Zustand: {{ $bundle->condition_range }}
                                        </div>

                                        {{-- Match-Hinweis --}}
                                        @if($bundle->matching_count > 0)
                                            <div class="mt-2 inline-flex items-center gap-1.5 px-2.5 py-1 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-full text-sm font-medium">
                                                <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                </svg>
                                                {{ $bundle->matching_count }} von {{ $bundle->total_count }} entsprechen deinen Gesuchen!
                                            </div>
                                        @endif
                                    </div>

                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        @if((int) $bundle->user_id === (int) auth()->id())
                                            <a href="{{ route('romantausch.edit-bundle', $bundle->bundle_id) }}"
                                                class="px-3 py-1.5 text-sm bg-gray-600 text-white rounded hover:bg-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-gray-500">
                                                Bearbeiten
                                            </a>
                                        @endif
                                        <button type="button" @click="expanded = !expanded"
                                            class="px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-500 text-gray-700 dark:text-gray-200 rounded hover:bg-gray-200 dark:hover:bg-gray-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-gray-400"
                                            :aria-expanded="expanded.toString()">
                                            <span x-show="!expanded">Details</span>
                                            <span x-show="expanded" x-cloak>Einklappen</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {{-- Aufgeklappte Details --}}
                            <div x-show="expanded" x-cloak x-collapse class="border-t border-gray-200 dark:border-gray-600">
                                {{-- Fotos --}}
                                @if(count($bundle->photos ?? []) > 0)
                                    <div class="p-4 bg-gray-50 dark:bg-gray-800 flex gap-3 overflow-x-auto">
                                        @foreach($bundle->photos as $photo)
                                            <img src="{{ asset('storage/' . $photo) }}"
                                                alt="Foto des Stapel-Angebots"
                                                class="w-24 h-24 object-cover rounded flex-shrink-0">
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Romanliste --}}
                                <div class="p-4 max-h-64 overflow-y-auto">
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Enthaltene Romane:</p>
                                    <ul class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 text-sm">
                                        @foreach($bundle->offers as $offer)
                                            @php
                                                $isMatch = $bundle->matching_offers->contains('id', $offer->id);
                                            @endphp
                                            <li @class([
                                                'px-2 py-1.5 rounded flex items-center justify-between gap-2',
                                                'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200' => $isMatch,
                                                'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300' => !$isMatch,
                                            ])>
                                                <span class="truncate" title="{{ $offer->book_title }}">
                                                    <strong>{{ $offer->book_number }}</strong> - {{ Str::limit($offer->book_title, 25) }}
                                                </span>
                                                @if($isMatch)
                                                    <span class="text-xs bg-green-200 dark:bg-green-800 px-1.5 py-0.5 rounded">Match</span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Angebote -->
        <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
                <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81]">Einzelne Angebote</h2>
                <div class="flex gap-2">
                    <a href="{{ route('romantausch.create-bundle-offer') }}"
                       class="inline-flex items-center px-4 py-2 bg-gray-700 dark:bg-gray-600 border border-transparent rounded-md font-semibold text-white hover:bg-gray-800 dark:hover:bg-gray-500 text-sm">
                        Stapel erstellen
                    </a>
                    <a href="{{ route('romantausch.create-offer') }}"
                       class="inline-flex items-center px-4 py-2 bg-[#8B0116] dark:bg-[#C41E3A] border border-transparent rounded-md font-semibold text-white hover:bg-[#A50019] dark:hover:bg-[#D63A4D]">
                        Angebot erstellen
                    </a>
                </div>
            </div>
            @if($offers->isEmpty())
                <p class="text-gray-600 dark:text-gray-400">Keine Einzelangebote vorhanden.</p>
            @else
                <ul class="space-y-3">
                    @foreach($offers as $offer)
                        @php
                            $bookDescription = trim($offer->series . ' ' . $offer->book_number . ' - ' . $offer->book_title);
                            $photos = $offer->photos ?? [];
                            $hasPhotos = count($photos) > 0;
                        @endphp
                        <li class="bg-gray-100 dark:bg-gray-700 p-4 rounded">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                                <div class="flex w-full flex-col gap-3 sm:w-48" {{ $hasPhotos ? 'data-romantausch-gallery' : '' }}>
                                    @if($hasPhotos)
                                        <ul class="grid grid-cols-3 gap-2 sm:grid-cols-2">
                                            @foreach($photos as $photoIndex => $photoPath)
                                                @php
                                                    $thumbnailSrc = asset('storage/' . $photoPath);
                                                    $photoNumber = $photoIndex + 1;
                                                    $thumbnailLabel = "Foto {$photoNumber} von {$bookDescription}";
                                                @endphp
                                                <li>
                                                    <button
                                                        type="button"
                                                        class="group relative block aspect-square w-full overflow-hidden rounded-md ring-1 ring-inset ring-gray-200 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#8B0116] dark:ring-gray-600 dark:focus-visible:ring-[#FF6B81]"
                                                        data-photo-dialog-trigger
                                                        data-photo-src="{{ $thumbnailSrc }}"
                                                        data-photo-alt="{{ $thumbnailLabel }}"
                                                        data-photo-index="{{ $photoIndex }}"
                                                        data-photo-label="{{ $thumbnailLabel }}"
                                                    >
                                                        <span class="sr-only">{{ $thumbnailLabel }} vergrößert anzeigen</span>
                                                        <img
                                                            src="{{ $thumbnailSrc }}"
                                                            alt="{{ $thumbnailLabel }}"
                                                            class="h-full w-full object-cover transition duration-200 group-hover:scale-105"
                                                            loading="lazy"
                                                        >
                                                    </button>
                                                </li>
                                            @endforeach
                                        </ul>
                                        <p class="text-xs text-gray-600 dark:text-gray-300">Zum Vergrößern ein Foto auswählen.</p>
                                        <div id="offer-{{ $offer->id }}-dialog-title" class="sr-only">Fotoansicht für {{ $bookDescription }}</div>
                                        <div
                                            class="hidden"
                                            data-photo-dialog
                                            role="dialog"
                                            aria-modal="true"
                                            aria-labelledby="offer-{{ $offer->id }}-dialog-title"
                                        >
                                            <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
                                                <div class="absolute inset-0 bg-black/70" data-photo-dialog-overlay aria-hidden="true"></div>
                                                <div
                                                    class="relative z-10 flex w-full max-w-3xl flex-col overflow-hidden rounded-lg bg-white shadow-2xl outline-none focus-visible:outline-none dark:bg-gray-900"
                                                    data-photo-dialog-panel
                                                    tabindex="-1"
                                                >
                                                    <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800">
                                                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $bookDescription }}</h3>
                                                        <button
                                                            type="button"
                                                            class="inline-flex items-center rounded-md p-2 text-gray-600 transition hover:text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#8B0116] dark:text-gray-300 dark:hover:text-white dark:focus-visible:ring-[#FF6B81]"
                                                            data-photo-dialog-close
                                                            data-photo-dialog-initial-focus
                                                            aria-label="Fotoansicht schließen"
                                                        >
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-5 w-5" aria-hidden="true">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="m6 6 12 12M18 6 6 18" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                    <div class="relative flex items-center justify-center bg-gray-900 px-8 py-10 dark:bg-black">
                                                        <button
                                                            type="button"
                                                            class="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-white/90 p-2 text-gray-800 shadow transition hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#8B0116] disabled:pointer-events-none disabled:opacity-50 dark:bg-gray-800/90 dark:text-gray-100 dark:hover:bg-gray-700 dark:focus-visible:ring-[#FF6B81]"
                                                            data-photo-dialog-prev
                                                            aria-label="Vorheriges Foto"
                                                        >
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-5 w-5" aria-hidden="true">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 19.5-7.5-7.5 7.5-7.5" />
                                                            </svg>
                                                        </button>
                                                        <img
                                                            src="{{ asset('storage/' . $photos[0]) }}"
                                                            alt="{{ "Foto 1 von {$bookDescription}" }}"
                                                            class="max-h-[70vh] w-full max-w-2xl object-contain"
                                                            data-photo-dialog-image
                                                        >
                                                        <button
                                                            type="button"
                                                            class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-white/90 p-2 text-gray-800 shadow transition hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#8B0116] disabled:pointer-events-none disabled:opacity-50 dark:bg-gray-800/90 dark:text-gray-100 dark:hover:bg-gray-700 dark:focus-visible:ring-[#FF6B81]"
                                                            data-photo-dialog-next
                                                            aria-label="Nächstes Foto"
                                                        >
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-5 w-5" aria-hidden="true">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                    <div class="flex items-center justify-between gap-3 border-t border-gray-200 bg-gray-50 px-4 py-3 text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                                        <span data-photo-dialog-counter>1 / {{ count($photos) }}</span>
                                                        <span data-photo-dialog-caption>{{ "Foto 1 von {$bookDescription}" }}</span>
                                                    </div>
                                                    <div class="flex justify-end border-t border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800">
                                                        <button
                                                            type="button"
                                                            class="inline-flex items-center rounded-md bg-[#8B0116] px-3 py-1.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#A50019] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#8B0116] dark:bg-[#C41E3A] dark:hover:bg-[#D63A4D] dark:focus-visible:ring-[#FF6B81]"
                                                            data-photo-dialog-close
                                                        >
                                                            Schließen
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <div
                                            class="flex h-24 w-24 items-center justify-center rounded-md border border-dashed border-gray-300 bg-gradient-to-br from-gray-50 to-gray-100 text-center text-xs font-medium text-gray-500 shadow-sm dark:border-gray-600 dark:from-gray-700 dark:to-gray-800 dark:text-gray-200"
                                            role="img"
                                            aria-label="Kein Foto vorhanden für {{ $bookDescription }}"
                                        >
                                            <div class="flex flex-col items-center gap-1">
                                                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5V7.125c0-.621.504-1.125 1.125-1.125H6.75m0 0L9 3.75h6l2.25 2.25m-11.25 0h11.25c.621 0 1.125.504 1.125 1.125V16.5c0 .621-.504 1.125-1.125 1.125H4.125A1.125 1.125 0 0 1 3 16.5Zm3.75-.375h.008v.008H6.75v-.008Zm0 0a2.625 2.625 0 1 0 5.25 0 2.625 2.625 0 0 0-5.25 0Zm9.75-7.5h.008v.008h-.008v-.008Z" />
                                                </svg>
                                                <span class="leading-tight">Kein Foto</span>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="flex flex-col gap-2">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $offer->series }} {{ $offer->book_number }} - {{ $offer->book_title }}</p>
                                            @if($offer->matches_user_request ?? false)
                                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800 ring-1 ring-inset ring-emerald-500/60 dark:bg-emerald-900/70 dark:text-emerald-100 dark:ring-emerald-400/60">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-3.5 w-3.5" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75 9 17.25l10.5-10.5" />
                                                    </svg>
                                                    <span>Passt zu deinem Gesuch</span>
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-sm text-gray-600 dark:text-gray-300">Zustand: {{ $offer->condition }}</p>
                                        <p class="text-sm text-gray-600 dark:text-gray-300">von <a href="{{ route('profile.view', $offer->user->id) }}" class="text-[#8B0116] hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#8B0116] dark:focus-visible:ring-[#FF6B81]">{{ $offer->user->name }}</a></p>
                                    </div>
                                    @if(auth()->id() === $offer->user_id)
                                        <div class="flex flex-wrap items-center gap-2">
                                            <a href="{{ route('romantausch.edit-offer', $offer) }}" class="inline-flex items-center gap-2 rounded px-3 py-1.5 text-sm font-semibold text-[#8B0116] dark:text-[#FF6B81] border border-transparent hover:border-[#8B0116] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#8B0116] dark:focus-visible:ring-[#FF6B81]" aria-label="Angebot bearbeiten: {{ $offer->series }} {{ $offer->book_number }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.862 4.487z" />
                                                </svg>
                                                <span>Bearbeiten</span>
                                            </a>
                                            <form method="POST" action="{{ route('romantausch.delete-offer', $offer) }}" class="inline">
                                                @csrf
                                                <button class="inline-flex items-center gap-2 rounded px-3 py-1.5 text-sm font-semibold text-red-600 dark:text-red-400 border border-transparent hover:border-red-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-red-500" onclick="return confirm('Möchtest du dieses Angebot wirklich löschen?')">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                    </svg>
                                                    <span>Löschen</span>
                                                </button>
                                            </form>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        <!-- Gesuche -->
        <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81]">Aktuelle Gesuche</h2>
                <a href="{{ route('romantausch.create-request') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-600 dark:bg-gray-500 border border-transparent rounded-md font-semibold text-white hover:bg-gray-700 dark:hover:bg-gray-400">
                    Gesuch erstellen
                </a>
            </div>
            @if($requests->isEmpty())
                <p class="text-gray-600 dark:text-gray-400">Keine Gesuche vorhanden.</p>
            @else
                <ul class="space-y-3">
                    @foreach($requests as $request)
                        <li class="bg-gray-100 dark:bg-gray-700 rounded p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="flex flex-col gap-2">
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $request->series }} {{ $request->book_number }} - {{ $request->book_title }} ({{ $request->condition }} oder besser)</span>
                                    @if($request->matches_user_offer ?? false)
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-sky-100 px-2.5 py-1 text-xs font-semibold text-sky-800 ring-1 ring-inset ring-sky-500/60 dark:bg-sky-900/60 dark:text-sky-100 dark:ring-sky-400/60">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-3.5 w-3.5" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6A2.25 2.25 0 0 0 5.25 5.25v13.5A2.25 2.25 0 0 0 7.5 21h9a2.25 2.25 0 0 0 2.25-2.25V9h-3Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 7.5h3" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 10.5h6" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 13.5h6" />
                                            </svg>
                                            <span>Passt zu deinem Angebot</span>
                                        </span>
                                    @endif
                                </div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <span class="text-sm text-gray-600 dark:text-gray-300">von <a href="{{ route('profile.view', $request->user->id) }}" class="text-[#8B0116] hover:underline">{{ $request->user->name }}</a></span>
                                    @if(auth()->id() === $request->user_id)
                                        <div class="flex items-center gap-2">
                                            <a href="{{ route('romantausch.edit-request', $request) }}" class="inline-flex items-center gap-2 rounded px-3 py-1.5 text-sm font-semibold text-[#8B0116] dark:text-[#FF6B81] border border-transparent hover:border-[#8B0116] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#8B0116] dark:focus-visible:ring-[#FF6B81]" aria-label="Gesuch bearbeiten: {{ $request->series }} {{ $request->book_number }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.862 4.487z" />
                                                </svg>
                                                <span>Bearbeiten</span>
                                            </a>
                                            <form method="POST" action="{{ route('romantausch.delete-request', $request) }}" class="inline">
                                                @csrf
                                                <button class="inline-flex items-center gap-2 rounded px-3 py-1.5 text-sm font-semibold text-red-600 dark:text-red-400 border border-transparent hover:border-red-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-red-500" onclick="return confirm('Möchtest du dieses Gesuch wirklich löschen?')">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                    </svg>
                                                    <span>Löschen</span>
                                                </button>
                                            </form>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        <!-- Abgeschlossene Tauschaktionen -->
        <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
            <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4">Erfolgreiche Tauschaktionen</h2>
            @if($completedSwaps->isEmpty())
                <p class="text-gray-600 dark:text-gray-400">Bisher wurden noch keine Tauschaktionen abgeschlossen.</p>
            @else
                <ul class="space-y-3">
                    @foreach($completedSwaps as $swap)
                        <li class="bg-gray-100 dark:bg-gray-700 p-3 rounded">
                            <span class="font-semibold">{{ $swap->offer->series }} {{ $swap->offer->book_number }} - {{ $swap->offer->book_title }}</span><br>
                            Getauscht zwischen <a href="{{ route('profile.view', $swap->offer->user->id) }}" class="text-[#8B0116] hover:underline">{{ $swap->offer->user->name }}</a> und <a href="{{ route('profile.view', $swap->request->user->id) }}" class="text-[#8B0116] hover:underline">{{ $swap->request->user->name }}</a> am {{ $swap->completed_at->format('d.m.Y') }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </x-member-page>
</x-app-layout>
