<x-app-layout>
    <x-member-page class="max-w-4xl">
            <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
                <h1 class="text-2xl font-semibold text-[#8B0116] dark:text-[#ff4b63] mb-6">Belohnungen</h1>
                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                    Dein aktuelles Baxx-Guthaben: <span class="font-semibold">{{ $userPoints }}</span>
                </p>
                <div class="space-y-4">
                    @foreach($rewards as $reward)
                        @php
                            $unlocked = $userPoints >= $reward['points'];
                        @endphp
                        <div class="p-4 rounded-lg shadow {{ $unlocked ? 'bg-white dark:bg-gray-700' : 'bg-gray-100 dark:bg-gray-700 opacity-50' }}">
                            <h2 class="text-lg font-semibold text-[#8B0116] dark:text-[#ff4b63] mb-1">{{ $reward['title'] }}</h2>
                            <p class="text-gray-700 dark:text-gray-300">{{ $reward['description'] }}</p>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Erforderliche Baxx: {{ $reward['points'] }}</p>
                            @if(isset($reward['percentage']))
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $reward['percentage'] }}% der Mitglieder haben diese Belohnung freigeschaltet
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
    </x-member-page>
</x-app-layout>