<x-app-layout>
    <x-member-page class="max-w-4xl">
            <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
                <h1 class="text-2xl font-bold text-[#8B0116] dark:text-[#FF6B81] mb-6">Angebotsdetails</h1>

                <p class="mb-4">{{ $offer->series }} {{ $offer->book_number }} - {{ $offer->book_title }} ({{ $offer->condition }})</p>

                @if(!empty($offer->photos))
                    <div class="flex flex-wrap gap-4 mb-4">
                        @foreach($offer->photos as $photo)
                            <img src="{{ asset('storage/' . $photo) }}" alt="Angebotsfoto" class="w-32 h-auto rounded">
                        @endforeach
                    </div>
                @endif

                <a href="{{ route('romantausch.index') }}" class="text-[#8B0116] hover:underline">Zurück zur Übersicht</a>
            </div>
    </x-member-page>
</x-app-layout>
