<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 p-4 bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-600 text-green-800 dark:text-green-200 rounded">
                    {{ session('success') }}
                </div>
            @endif
            <!-- Kopfzeile mit Buttons -->
            <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <h1 class="text-2xl font-semibold text-[#8B0116] dark:text-[#FF6B81]">Romantauschbörse</h1>
                <div class="flex gap-2">
                    <a href="{{ route('romantausch.create-offer') }}"
                       class="inline-flex items-center px-4 py-2 bg-[#8B0116] dark:bg-[#C41E3A] border border-transparent rounded-md font-semibold text-white hover:bg-[#A50019] dark:hover:bg-[#D63A4D]">
                        Angebot erstellen
                    </a>
                    <a href="{{ route('romantausch.create-request') }}"
                       class="inline-flex items-center px-4 py-2 bg-gray-600 dark:bg-gray-500 border border-transparent rounded-md font-semibold text-white hover:bg-gray-700 dark:hover:bg-gray-400">
                        Gesuch erstellen
                    </a>
                </div>
            </div>
            @if($activeSwaps->isNotEmpty())
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-2">Deine Matches</h2>
                    <p class="mb-4 text-gray-600 dark:text-gray-400">Kontaktiert euch gegenseitig über die angezeigten Mailadressen und klickt anschließend auf „Tausch abgeschlossen“. Für jeden abgeschlossenen Tausch gibt es <strong>2 Baxx</strong>!</p>
                    <ul class="space-y-4">
                        @foreach($activeSwaps as $swap)
                            <li class="bg-gray-100 dark:bg-gray-700 p-3 rounded">
                                <div class="font-semibold mb-1">{{ $swap->offer->book_number }} - {{ $swap->offer->book_title }}</div>
                                <div class="text-sm text-gray-600 dark:text-gray-300 mb-2">
                                    {{ $swap->offer->user->name }} ({{ $swap->offer->user->email }}) ↔ {{ $swap->request->user->name }} ({{ $swap->request->user->email }})
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
                <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4">Aktuelle Angebote</h2>
                @if($offers->isEmpty())
                    <p class="text-gray-600 dark:text-gray-400">Keine Angebote vorhanden.</p>
                @else
                    <ul class="space-y-3">
                        @foreach($offers as $offer)
                            <li class="bg-gray-100 dark:bg-gray-700 p-3 rounded flex justify-between items-center">
                                <span>{{ $offer->book_number }} - {{ $offer->book_title }} ({{ $offer->condition }})</span>
                                <span class="text-sm text-gray-600 dark:text-gray-300">von {{ $offer->user->name }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <!-- Gesuche -->
            <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4">Aktuelle Gesuche</h2>
                @if($requests->isEmpty())
                    <p class="text-gray-600 dark:text-gray-400">Keine Gesuche vorhanden.</p>
                @else
                    <ul class="space-y-3">
                        @foreach($requests as $request)
                            <li class="bg-gray-100 dark:bg-gray-700 p-3 rounded flex justify-between items-center">
                                <span>{{ $request->book_number }} - {{ $request->book_title }} ({{ $request->condition }} oder besser)</span>
                                <span class="text-sm text-gray-600 dark:text-gray-300">von {{ $request->user->name }}</span>
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
                                <span class="font-semibold">{{ $swap->offer->book_number }} - {{ $swap->offer->book_title }}</span><br>
                                Getauscht zwischen {{ $swap->offer->user->name }} und {{ $swap->request->user->name }} am {{ $swap->completed_at->format('d.m.Y') }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
