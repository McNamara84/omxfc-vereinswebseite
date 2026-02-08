<x-app-layout>
    <x-member-page class="max-w-4xl">
        <x-header title="Belohnungen">
            <x-slot:subtitle>
                Dein aktuelles Baxx-Guthaben: <x-badge :value="$userPoints" class="badge-primary" />
            </x-slot:subtitle>
        </x-header>

        <div class="space-y-4">
            @foreach($rewards as $reward)
                @php $unlocked = $userPoints >= $reward['points']; @endphp
                <x-card shadow class="{{ $unlocked ? '' : 'opacity-50' }}">
                    <div class="flex items-start justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-primary">{{ $reward['title'] }}</h2>
                            <p class="text-base-content">{{ $reward['description'] }}</p>
                            @if(isset($reward['percentage']))
                                <p class="mt-1 text-sm text-base-content">
                                    {{ $reward['percentage'] }}% der Mitglieder haben diese Belohnung freigeschaltet
                                </p>
                            @endif
                        </div>
                        <x-badge :value="$reward['points'] . ' Baxx'" class="{{ $unlocked ? 'badge-success' : 'badge-ghost' }}" />
                    </div>
                </x-card>
            @endforeach
        </div>
    </x-member-page>
</x-app-layout>