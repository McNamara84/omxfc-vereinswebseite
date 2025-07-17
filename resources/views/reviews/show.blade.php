<x-app-layout>
    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 p-4 bg-green-100 dark:bg-green-800 border border-green-400 dark:border-green-600 text-green-800 dark:text-green-100 rounded">
                    {{ session('success') }}
                </div>
            @endif

            <h1 class="text-2xl font-semibold text-[#8B0116] dark:text-[#FCA5A5] mb-6">
                Rezensionen zu „{{ $book->title }}“ (Nr. {{ $book->roman_number }})
            </h1>

            @forelse($reviews as $review)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">{{ $review->title }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">von {{ $review->user->name }} am {{ $review->created_at->format('d.m.Y') }}</p>
                    <div class="mt-4 text-gray-800 dark:text-gray-200 whitespace-pre-line">
                        {{ $review->content }}
                    </div>

                    @if(auth()->id() === $review->user_id && auth()->user()->hasAnyRole(['Mitglied','Ehrenmitglied','Kassenwart']))
                        <div class="mt-4 flex space-x-2">
                            <a href="{{ route('reviews.edit', $review) }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                                Rezension bearbeiten
                            </a>
                            <form action="{{ route('reviews.destroy', $review) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded">
                                    Rezension löschen
                                </button>
                            </form>
                        </div>
                    @elseif(auth()->user()->hasAnyRole(['Vorstand','Admin']))
                        <form action="{{ route('reviews.destroy', $review) }}" method="POST" class="mt-4">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded">
                                Rezension löschen
                            </button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="text-gray-600 dark:text-gray-400">Noch keine Rezensionen vorhanden.</p>
            @endforelse

            <a href="{{ route('reviews.index') }}" class="text-[#8B0116] hover:underline">← Zurück zur Übersicht</a>
        </div>
    </div>
</x-app-layout>