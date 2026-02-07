<x-app-layout title="Meetings – Offizieller MADDRAX Fanclub e. V." description="Übersicht regelmäßiger AG-Termine und Stammtische.">
    <x-member-page class="max-w-4xl">
        <x-header title="Meetings" />

        <x-card shadow>
            <ul class="space-y-6">
                @foreach($meetings as $meeting)
                    <li class="border border-base-content/10 rounded-md p-4">
                        <h2 class="text-xl font-bold">{{ $meeting['name'] }}</h2>
                        <p>
                            <strong>Wann:</strong>
                            @if($meeting['day'] === 'see_note')
                                Jeden zweiten Dienstag nach einem Roman (Beginn: {{ $meeting['time_from'] }})
                            @else
                                {{ $meeting['next']->translatedFormat('l, d.m.Y') }}
                                von {{ $meeting['time_from'] }} bis {{ $meeting['time_to'] }}
                            @endif
                        </p>
                        <div class="mt-4">
                            <form method="POST" action="{{ route('meetings.redirect') }}">
                                @csrf
                                <input type="hidden" name="meeting" value="maddraxikon">
                                <x-button type="submit" label="Zoom-Meeting betreten" icon="o-video-camera" class="btn-primary btn-sm" />
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-card>
    </x-member-page>
</x-app-layout>