<x-app-layout>
    <div class="pb-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
                <h1 class="text-2xl font-semibold text-[#8B0116] dark:text-[#ff4b63] mb-6">Protokolle</h1>

                <div id="accordion">
                    @foreach($protokolle as $jahr => $dokumente)
                        <div class="mb-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                            <h2>
                                <button type="button"
                                    class="w-full flex justify-between items-center bg-gray-100 dark:bg-gray-700 px-4 py-3 rounded-t-lg font-semibold"
                                    onclick="toggleAccordion({{ $jahr }})">
                                    Protokolle {{ $jahr }}
                                    <span id="icon-{{ $jahr }}">+</span>
                                </button>
                            </h2>

                            <div id="content-{{ $jahr }}" class="hidden bg-white dark:bg-gray-900 px-4 py-2 rounded-b-lg">
                                <ul class="space-y-2">
                                    @foreach($dokumente as $protokoll)
                                        <li>
                                            <a href="{{ route('protokolle.download', $protokoll['datei']) }}" class="text-blue-600 hover:underline">
                                                {{ $protokoll['datum'] }} – {{ $protokoll['titel'] }}
                                            </a>
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

    <script>
        function toggleAccordion(jahr) {
            const content = document.getElementById('content-' + jahr);
            const icon = document.getElementById('icon-' + jahr);
            content.classList.toggle('hidden');
            icon.textContent = content.classList.contains('hidden') ? '+' : '-';
        }
    </script>
</x-app-layout>
