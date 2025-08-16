<x-app-layout>
    <x-member-page>
        @if(session('status'))
            <div class="mb-4 p-4 bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-600 text-green-800 dark:text-green-200 rounded">
                {{ session('status') }}
            </div>
        @endif
        <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6 flex justify-between items-center">
            <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81]">Hörbuchfolgen</h2>
            <a href="{{ route('hoerbuecher.create') }}" class="inline-flex items-center px-4 py-2 bg-[#8B0116] dark:bg-[#C41E3A] border border-transparent rounded-md font-semibold text-white hover:bg-[#A50019] dark:hover:bg-[#D63A4D] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#8B0116] dark:focus:ring-[#FF6B81]">
                Neue Folge
            </a>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Folge</th>
                            <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Titel</th>
                            <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Veröffentlichung</th>
                            <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Status</th>
                            <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Fortschritt</th>
                            <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Bemerkungen</th>
                            <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Verantwortlich</th>
                            <th class="px-4 py-2 text-center text-gray-800 dark:text-gray-200">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($episodes as $episode)
                            <tr>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $episode->episode_number }}</td>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $episode->title }}</td>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $episode->planned_release_date->format('d.m.Y') }}</td>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $episode->status }}</td>
                                <td class="px-4 py-2">
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-4">
                                        <div class="h-4 rounded-full text-xs font-medium text-center leading-none text-white" style="width: {{ $episode->progress }}%; background-color: hsl({{ $episode->progress * 1.2 }}, 100%, 40%);">
                                            {{ $episode->progress }}%
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $episode->notes }}</td>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $episode->responsible?->name }}</td>
                                <td class="px-4 py-2 text-center">
                                    <a href="{{ route('hoerbuecher.edit', $episode) }}" class="text-blue-600 dark:text-blue-400 hover:underline">Bearbeiten</a>
                                    <form action="{{ route('hoerbuecher.destroy', $episode) }}" method="POST" class="inline-block ml-2" onsubmit="return confirm('Wirklich löschen?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 dark:text-red-400 hover:underline">Löschen</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-2 text-center text-gray-700 dark:text-gray-300">Keine Hörbuchfolgen vorhanden.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-member-page>
</x-app-layout>
