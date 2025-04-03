<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if(session('status'))
                <div
                    class="mb-4 p-4 bg-green-100 dark:bg-green-800 border border-green-400 dark:border-green-600 text-green-800 dark:text-green-100 rounded">
                    {{ session('status') }}
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <!-- Mitgliederzahl Card -->
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FCA5A5] mb-4">Aktuelle Mitgliederzahl
                    </h2>
                    <div class="text-5xl font-bold text-gray-800 dark:text-gray-200">
                        {{ $memberCount }}
                    </div>
                </div>

                <!-- Anwärter-Liste für Kassenwart, Vorstand und Admin -->
                @if($anwaerter->isNotEmpty())
                    <div class="md:col-span-2 bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
                        <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FCA5A5] mb-4">Mitgliedsanträge</h2>

                        <!-- Desktop-Ansicht (versteckt auf Mobilgeräten) -->
                        <div class="hidden md:block overflow-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-2 text-left text-gray-700 dark:text-gray-300">Name</th>
                                        <th class="px-4 py-2 text-left text-gray-700 dark:text-gray-300">E-Mail</th>
                                        <th class="px-4 py-2 text-left text-gray-700 dark:text-gray-300">Beitrag</th>
                                        <th class="px-4 py-2 text-center text-gray-700 dark:text-gray-300">Aktion</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($anwaerter as $person)
                                        <tr>
                                            <td class="px-4 py-2 text-gray-800 dark:text-gray-200">{{ $person->name }}</td>
                                            <td class="px-4 py-2 text-gray-800 dark:text-gray-200">{{ $person->email }}</td>
                                            <td class="px-4 py-2 text-gray-800 dark:text-gray-200">
                                                {{ $person->mitgliedsbeitrag }}</td>
                                            <td class="px-4 py-2 flex justify-center gap-2">
                                                <form action="{{ route('anwaerter.approve', $person->id) }}" method="POST">
                                                    @csrf
                                                    <button type="submit"
                                                        class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded">
                                                        Genehmigen
                                                    </button>
                                                </form>
                                                <form action="{{ route('anwaerter.reject', $person->id) }}" method="POST"
                                                    onsubmit="return confirm('Antrag wirklich ablehnen und Nutzer löschen?');">
                                                    @csrf
                                                    <button type="submit"
                                                        class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded">
                                                        Ablehnen
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Mobile-Ansicht (nur auf Mobilgeräten sichtbar) -->
                        <div class="md:hidden space-y-6">
                            @foreach($anwaerter as $person)
                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg shadow">
                                    <div class="mb-2">
                                        <span class="font-semibold text-gray-700 dark:text-gray-300">Name:</span>
                                        <span class="block mt-1 text-gray-800 dark:text-gray-200">{{ $person->name }}</span>
                                    </div>
                                    <div class="mb-2">
                                        <span class="font-semibold text-gray-700 dark:text-gray-300">E-Mail:</span>
                                        <span
                                            class="block mt-1 break-words text-gray-800 dark:text-gray-200">{{ $person->email }}</span>
                                    </div>
                                    <div class="mb-4">
                                        <span class="font-semibold text-gray-700 dark:text-gray-300">Beitrag:</span>
                                        <span
                                            class="block mt-1 text-gray-800 dark:text-gray-200">{{ $person->mitgliedsbeitrag }}</span>
                                    </div>
                                    <div class="flex gap-2 mt-4">
                                        <form action="{{ route('anwaerter.approve', $person->id) }}" method="POST"
                                            class="w-1/2">
                                            @csrf
                                            <button type="submit"
                                                class="w-full bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded">
                                                Genehmigen
                                            </button>
                                        </form>
                                        <form action="{{ route('anwaerter.reject', $person->id) }}" method="POST" class="w-1/2"
                                            onsubmit="return confirm('Antrag wirklich ablehnen und Nutzer löschen?');">
                                            @csrf
                                            <button type="submit"
                                                class="w-full bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded">
                                                Ablehnen
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>
</x-app-layout>