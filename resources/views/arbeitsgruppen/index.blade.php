<x-app-layout title="Arbeitsgruppen – Offizieller MADDRAX Fanclub e. V." description="Tabellarische Übersicht aller Arbeitsgruppen.">
    <x-member-page>
        <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
            <div class="flex justify-between items-center {{ request()->routeIs('ag.index') ? 'mb-4' : 'mb-6' }}">
                <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81]">Arbeitsgruppen</h2>
                @if(Auth::user()->hasRole('Admin'))
                    <a href="{{ route('arbeitsgruppen.create') }}"
                       class="inline-flex items-center px-4 py-2 bg-[#8B0116] dark:bg-[#C41E3A] border border-transparent rounded-md font-semibold text-white hover:bg-[#A50019] dark:hover:bg-[#D63A4D]">
                        AG erstellen
                    </a>
                @endif
            </div>
            @if(request()->routeIs('ag.index') && Auth::user()->ownedTeams()->where('personal_team', false)->exists())
                <p class="mb-6 text-gray-700 dark:text-gray-300">Als AG-Leiter kannst du hier deine AG verwalten. Die Beschreibung, das Logo, der Termin für das regelmäßige AG-Treffen und die angegebene E-Mail-Adresse werden auch im öffentlichen Bereich für Nicht-Mitglieder angezeigt. Bitte halte diese Informationen daher stets aktuell.</p>
            @endif
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Name</th>
                            <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Leitung</th>
                            <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">E-Mail</th>
                            <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Termin</th>
                            <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($ags as $ag)
                            <tr>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $ag->name }}</td>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $ag->owner?->name ?? '-' }}</td>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $ag->email ?? '-' }}</td>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $ag->meeting_schedule ?? '-' }}</td>
                                <td class="px-4 py-2">
                                    <a href="{{ route('arbeitsgruppen.edit', $ag) }}" class="text-[#8B0116] dark:text-[#FF6B81] hover:underline">Bearbeiten</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-2 text-center text-gray-700 dark:text-gray-300">Keine Arbeitsgruppen vorhanden.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-member-page>
</x-app-layout>
