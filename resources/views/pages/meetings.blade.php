<x-app-layout>
    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-maddrax-black border border-maddrax-red shadow-xl sm:rounded-lg p-6">
            <h1 class="text-2xl font-semibold text-[#8B0116] dark:text-[#ff4b63] mb-6">Meetings</h1>

            <ul class="space-y-6">
                @foreach($meetings as $meeting)
                    <li class="border border-gray-300 dark:border-gray-600 rounded-md p-4">
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
                                <x-button type="submit">Zoom-Meeting betreten</x-button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</x-app-layout>