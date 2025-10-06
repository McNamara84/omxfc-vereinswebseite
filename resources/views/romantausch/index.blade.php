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
                        {{ __('romantausch.info.title') }}
                    </h2>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 max-w-3xl">
                        {{ __('romantausch.info.intro') }}
                    </p>
                </div>
            </div>
            <ol class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4" aria-label="{{ __('romantausch.info.steps_aria_label') }}">
                <li class="flex h-full flex-col gap-3 rounded-lg border border-gray-200 bg-white/60 p-4 dark:border-gray-700 dark:bg-gray-800/60">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-[#8B0116] text-sm font-semibold text-white dark:bg-[#FF6B81]">1</span>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ __('romantausch.info.steps.offer.title') }}
                        </h3>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        {{ __('romantausch.info.steps.offer.description') }}
                    </p>
                    <div class="mt-auto">
                        <a href="{{ route('romantausch.create-offer') }}"
                           class="inline-flex items-center justify-center rounded-md bg-[#8B0116] px-4 py-2 text-sm font-semibold text-white transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#8B0116] hover:bg-[#A50019] dark:bg-[#C41E3A] dark:hover:bg-[#D63A4D] dark:focus-visible:ring-[#FF6B81]"
                           aria-label="{{ __('romantausch.info.steps.offer.cta_aria') }}">
                            {{ __('romantausch.info.steps.offer.cta') }}
                        </a>
                    </div>
                </li>
                <li class="flex h-full flex-col gap-3 rounded-lg border border-gray-200 bg-white/60 p-4 dark:border-gray-700 dark:bg-gray-800/60">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-[#8B0116] text-sm font-semibold text-white dark:bg-[#FF6B81]">2</span>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ __('romantausch.info.steps.request.title') }}
                        </h3>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        {{ __('romantausch.info.steps.request.description') }}
                    </p>
                    <div class="mt-auto">
                        <a href="{{ route('romantausch.create-request') }}"
                           class="inline-flex items-center justify-center rounded-md bg-gray-700 px-4 py-2 text-sm font-semibold text-white transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-gray-700 hover:bg-gray-800 dark:bg-gray-500 dark:hover:bg-gray-400 dark:focus-visible:ring-[#FF6B81]"
                           aria-label="{{ __('romantausch.info.steps.request.cta_aria') }}">
                            {{ __('romantausch.info.steps.request.cta') }}
                        </a>
                    </div>
                </li>
                <li class="flex h-full flex-col gap-3 rounded-lg border border-gray-200 bg-white/60 p-4 dark:border-gray-700 dark:bg-gray-800/60">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-[#8B0116] text-sm font-semibold text-white dark:bg-[#FF6B81]">3</span>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ __('romantausch.info.steps.match.title') }}
                        </h3>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        {{ __('romantausch.info.steps.match.description') }}
                    </p>
                </li>
                <li class="flex h-full flex-col gap-3 rounded-lg border border-gray-200 bg-white/60 p-4 dark:border-gray-700 dark:bg-gray-800/60">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-[#8B0116] text-sm font-semibold text-white dark:bg-[#FF6B81]">4</span>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ __('romantausch.info.steps.confirm.title') }}
                        </h3>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        {{ __('romantausch.info.steps.confirm.description') }}
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
        <!-- Angebote -->
        <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81]">Aktuelle Angebote</h2>
                <a href="{{ route('romantausch.create-offer') }}"
                   class="inline-flex items-center px-4 py-2 bg-[#8B0116] dark:bg-[#C41E3A] border border-transparent rounded-md font-semibold text-white hover:bg-[#A50019] dark:hover:bg-[#D63A4D]">
                    Angebot erstellen
                </a>
            </div>
            @if($offers->isEmpty())
                <p class="text-gray-600 dark:text-gray-400">Keine Angebote vorhanden.</p>
            @else
                <ul class="space-y-3">
                    @foreach($offers as $offer)
                        @php
                            $bookDescription = trim($offer->series . ' ' . $offer->book_number . ' - ' . $offer->book_title);
                            $photos = $offer->photos;
                            $firstPhoto = $photos ? reset($photos) ?: null : null;
                        @endphp
                        <li class="bg-gray-100 dark:bg-gray-700 p-4 rounded">
                            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                                <div class="flex-shrink-0">
                                    @if($firstPhoto)
                                        <img
                                            src="{{ asset('storage/' . $firstPhoto) }}"
                                            alt="Cover von {{ $bookDescription }}"
                                            class="h-24 w-24 rounded-md object-cover shadow-sm ring-1 ring-inset ring-gray-200 dark:ring-gray-600"
                                            loading="lazy"
                                        >
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
                                <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ $offer->series }} {{ $offer->book_number }} - {{ $offer->book_title }}</p>
                                        <p class="text-sm text-gray-600 dark:text-gray-300">Zustand: {{ $offer->condition }}</p>
                                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">von <a href="{{ route('profile.view', $offer->user->id) }}" class="text-[#8B0116] hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#8B0116] dark:focus-visible:ring-[#FF6B81]">{{ $offer->user->name }}</a></p>
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
                        <li class="bg-gray-100 dark:bg-gray-700 p-3 rounded flex justify-between items-center">
                            <span>{{ $request->series }} {{ $request->book_number }} - {{ $request->book_title }} ({{ $request->condition }} oder besser)</span>
                            <div class="flex items-center gap-3">
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
