<x-app-layout title="Downloads – Offizieller MADDRAX Fanclub e. V." description="Exklusive Dateien wie Bauanleitungen und Fanstories für Vereinsmitglieder.">
    <div class="pb-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
                <h1 class="text-2xl font-semibold text-[#8B0116] dark:text-[#ff4b63] mb-6">
                    Downloads
                </h1>

                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                    Deine Baxx: <span class="font-semibold">{{ $userPoints }}</span>
                </p>

                <div id="accordion">
                    @foreach($downloads as $kategorie => $files)
                        @php $id = \Illuminate\Support\Str::slug($kategorie); @endphp

                        <div class="mb-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                            {{-- Kopf/Falte --}}
                            <h2>
                                <button
                                    type="button"
                                    class="w-full flex justify-between items-center bg-gray-100 dark:bg-gray-700 px-4 py-3 rounded-t-lg font-semibold"
                                    onclick="toggleAccordion('{{ $id }}')"
                                >
                                    {{ $kategorie }}
                                    <span id="icon-{{ $id }}">+</span>
                                </button>
                            </h2>

                            {{-- Inhalt --}}
                            <div id="content-{{ $id }}" class="hidden bg-white dark:bg-gray-900 px-4 py-2 rounded-b-lg">
                                <ul class="space-y-2">
                                    @foreach($files as $file)
                                        <li class="flex items-center justify-between">
                                            <span>
                                                {{ $file['titel'] }}
                                                <span class="ml-2 text-xs text-gray-500">({{ $file['punkte'] }} Baxx)</span>
                                            </span>

                                            @if($userPoints >= $file['punkte'])
                                                <a
                                                    href="{{ route('downloads.download', $file['datei']) }}"
                                                    class="text-blue-600 hover:underline flex items-center"
                                                >
                                                    Herunterladen
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" />
                                                    </svg>
                                                </a>
                                            @else
                                                <span class="text-gray-400 flex items-center" title="Mehr Baxx nötig">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 12H6" />
                                                    </svg>
                                                    Gesperrt
                                                </span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- kleines Vanilla‑JS‑Accordion --}}
    <script>
        function toggleAccordion(id) {
            const content = document.getElementById('content-' + id);
            const icon    = document.getElementById('icon-' + id);
            content.classList.toggle('hidden');
            icon.textContent = content.classList.contains('hidden') ? '+' : '-';
        }
    </script>
</x-app-layout>
