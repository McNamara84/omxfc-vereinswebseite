<x-app-layout>
    <x-member-page class="max-w-3xl">
        <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
            <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-6">{{ $episode->title }}</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><span class="font-medium">Folge:</span> {{ $episode->episode_number }}</div>
                <div><span class="font-medium">Autor:</span> {{ $episode->author }}</div>
                <div><span class="font-medium">Ziel-EVT:</span> {{ $episode->planned_release_date }}</div>
                <div><span class="font-medium">Status:</span> {{ $episode->status->value }}</div>
                <div class="md:col-span-2">
                    <span class="font-medium">Fortschritt:</span>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-4 mt-1">
                        <div class="h-4 rounded-full text-xs font-medium text-center leading-none text-white" style="width: {{ $episode->progress }}%; background-color: hsl({{ $episode->progressHue() }}, 100%, 40%);">
                            {{ $episode->progress }}%
                        </div>
                    </div>
                </div>
                <div class="md:col-span-2">
                    <span class="font-medium">Rollen besetzt:</span>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-4 mt-1">
                        <div class="h-4 rounded-full text-xs font-medium text-center leading-none text-white" style="width: {{ $episode->rolesFilledPercent() }}%; background-color: hsl({{ $episode->rolesHue() }}, 100%, 40%);">
                            {{ $episode->roles_filled }}/{{ $episode->roles_total }}
                        </div>
                    </div>
                </div>
                @if($episode->roles->isNotEmpty())
                <div class="md:col-span-2">
                    <span class="font-medium">Rollen:</span>
                    <div class="mt-1 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-100 dark:bg-gray-700">
                                <tr>
                                    <th class="px-2 py-1 text-left">Rolle</th>
                                    <th class="px-2 py-1 text-left">Beschreibung</th>
                                    <th class="px-2 py-1 text-left">Takes</th>
                                    <th class="px-2 py-1 text-left">Sprecher</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($episode->roles as $role)
                                <tr class="border-t border-gray-200 dark:border-gray-700">
                                    <td class="px-2 py-1">{{ $role->name }}</td>
                                    <td class="px-2 py-1">{{ $role->description }}</td>
                                    <td class="px-2 py-1">{{ $role->takes }}</td>
                                    <td class="px-2 py-1">
                                        {{ $role->user?->name ?? $role->speaker_name ?? '-' }}
                                        @php($prev = $previousSpeakers[$role->name] ?? null)
                                        @if($prev)
                                            <div class="text-xs text-gray-500">Bisheriger Sprecher: {{ $prev }}</div>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif
                <div class="md:col-span-2"><span class="font-medium">Verantwortlich:</span> {{ $episode->responsible?->name ?? '-' }}</div>
                <div class="md:col-span-2">
                    <span class="font-medium">Anmerkungen:</span>
                    <p class="mt-1">{{ $episode->notes }}</p>
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <a href="{{ route('hoerbuecher.edit', $episode) }}" class="text-blue-600 dark:text-blue-400 hover:underline">Bearbeiten</a>
                <x-confirm-delete :action="route('hoerbuecher.destroy', $episode)" />
            </div>
            <div class="mt-6">
                <a href="{{ route('hoerbuecher.index') }}" class="text-gray-600 dark:text-gray-400 hover:underline">&laquo; Zurück zur Übersicht</a>
            </div>
        </div>
    </x-member-page>
</x-app-layout>
