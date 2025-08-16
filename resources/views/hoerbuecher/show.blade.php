<x-app-layout>
    <x-member-page class="max-w-3xl">
        <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
            <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-6">{{ $episode->title }}</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><span class="font-medium">Folge:</span> {{ $episode->episode_number }}</div>
                <div><span class="font-medium">Autor:</span> {{ $episode->author }}</div>
                <div><span class="font-medium">Ziel-EVT:</span> {{ $episode->planned_release_date }}</div>
                <div><span class="font-medium">Status:</span> {{ $episode->status }}</div>
                <div class="md:col-span-2">
                    <span class="font-medium">Fortschritt:</span>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-4 mt-1">
                        <div class="h-4 rounded-full text-xs font-medium text-center leading-none text-white" style="width: {{ $episode->progress }}%; background-color: hsl({{ $episode->progressHue() }}, 100%, 40%);">
                            {{ $episode->progress }}%
                        </div>
                    </div>
                </div>
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
