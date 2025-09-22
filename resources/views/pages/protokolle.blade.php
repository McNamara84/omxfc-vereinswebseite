<x-app-layout title="Protokolle – Offizieller MADDRAX Fanclub e. V." description="Versammlungsprotokolle als PDF zum Download.">
    <div class="pb-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
                <h1 class="text-2xl font-semibold text-[#8B0116] dark:text-[#ff4b63] mb-6">Protokolle</h1>

                <div id="accordion" data-protokolle-accordion>
                    @foreach($protokolle as $jahr => $dokumente)
                        <details class="mb-4 border border-gray-200 dark:border-gray-700 rounded-lg" data-protokolle-accordion-item>
                            <summary
                                id="accordion-trigger-{{ $jahr }}"
                                class="list-none w-full flex justify-between items-center gap-4 bg-gray-100 dark:bg-gray-700 px-4 py-3 rounded-t-lg font-semibold text-left cursor-pointer focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                                data-protokolle-accordion-button
                                aria-controls="content-{{ $jahr }}"
                                aria-expanded="false"
                                role="button"
                            >
                                <span class="flex flex-col sm:flex-row sm:items-center sm:gap-2">
                                    <span>Protokolle {{ $jahr }}</span>
                                    <span class="text-sm font-normal text-gray-600 dark:text-gray-300 sm:mt-0 mt-1">
                                        {{ count($dokumente) }} {{ count($dokumente) === 1 ? 'Dokument' : 'Dokumente' }}
                                    </span>
                                </span>
                                <span class="flex items-center gap-2 text-xl" aria-hidden="true">
                                    <span data-protokolle-accordion-icon class="select-none">+</span>
                                </span>
                                <span class="sr-only" data-protokolle-accordion-label>Abschnitt Protokolle {{ $jahr }} umschalten</span>
                            </summary>

                            <div
                                id="content-{{ $jahr }}"
                                class="bg-white dark:bg-gray-900 px-4 py-2 rounded-b-lg"
                                role="region"
                                aria-labelledby="accordion-trigger-{{ $jahr }}"
                                aria-hidden="true"
                                data-protokolle-accordion-panel
                            >
                                <ul class="space-y-2">
                                    @foreach($dokumente as $protokoll)
                                        <li>
                                            <a
                                                href="{{ route('protokolle.download', $protokoll['datei']) }}"
                                                class="inline-flex items-center gap-2 text-blue-600 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                                            >
                                                <span aria-hidden="true">📄</span>
                                                <span>{{ $protokoll['datum'] }} – {{ $protokoll['titel'] }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </details>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
