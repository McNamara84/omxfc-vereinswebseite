<x-app-layout>
    <x-member-page>
        <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
            <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-6">Arbeitsgruppen</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Name</th>
                            <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Leitung</th>
                            <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">E-Mail</th>
                            <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Termin</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($ags as $ag)
                            <tr>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $ag->name }}</td>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $ag->owner->name }}</td>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $ag->email ?? '-' }}</td>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $ag->meeting_schedule ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-2 text-center text-gray-700 dark:text-gray-300">Keine Arbeitsgruppen vorhanden.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-member-page>
</x-app-layout>
