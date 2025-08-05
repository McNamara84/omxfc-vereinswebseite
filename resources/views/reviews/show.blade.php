<x-app-layout>
    <x-member-page class="max-w-3xl">
            @if(session('success'))
                <div class="mb-4 p-4 bg-green-100 dark:bg-green-800 border border-green-400 dark:border-green-600 text-green-800 dark:text-green-100 rounded">
                    {{ session('success') }}
                </div>
            @endif

            <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-6">
                Rezensionen zu „{{ $book->title }}“ (Nr. {{ $book->roman_number }})
            </h1>

            @forelse($reviews as $review)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">{{ $review->title }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">von {{ $review->user->name }} am {{ $review->created_at->format('d.m.Y') }}</p>
                    <div class="mt-4 text-gray-800 dark:text-gray-200 whitespace-pre-line">
                        {{ $review->content }}
                    </div>

                    @if(in_array($role ?? null, ['Vorstand','Admin'], true) || auth()->id() === $review->user_id)
                        <div class="mt-4 flex flex-col sm:flex-row gap-2">
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
                    @endif
                    <div class="mt-6">
                        @foreach($review->comments->whereNull('parent_id') as $comment)
                            @include('reviews.partials.comment', ['comment' => $comment, 'role' => $role, 'depth' => 0])
                        @endforeach

                        <form method="POST" action="{{ route('reviews.comments.store', $review) }}" class="mt-4">
                            @csrf
                            <textarea name="content" rows="2" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-800 rounded" placeholder="Kommentieren..." required></textarea>
                            <button type="submit" class="mt-2 bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded">Kommentar hinzufügen</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-gray-600 dark:text-gray-400">Noch keine Rezensionen vorhanden.</p>
            @endforelse

            <a href="{{ route('reviews.index') }}" class="text-[#8B0116] hover:underline">← Zurück zur Übersicht</a>
    </x-member-page>
</x-app-layout>