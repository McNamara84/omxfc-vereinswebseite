<x-app-layout :title="$title" :description="$description">
    <x-member-page class="max-w-3xl">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-6">
                Rezension zu „{{ $review->book->title }}“ bearbeiten
            </h1>

            <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
                <form action="{{ route('reviews.update', $review) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <x-forms.text-field
                        name="title"
                        label="Rezensionstitel"
                        :value="old('title', $review->title)"
                        required
                        class="mb-4"
                    />

                    <x-forms.textarea-field
                        name="content"
                        label="Rezensionstext"
                        :value="old('content', $review->content)"
                        rows="8"
                        help="Mindestens 140 Zeichen."
                        required
                        class="mb-4"
                    />

                    <div class="flex flex-col sm:flex-row sm:justify-between gap-4">
                        <a href="{{ route('reviews.show', $review->book) }}" class="text-gray-600 dark:text-gray-400 hover:underline">Abbrechen</a>
                        <button type="submit" class="bg-[#8B0116] dark:bg-[#FCA5A5] text-white px-4 py-2 rounded">
                            Rezension aktualisieren
                        </button>
                    </div>
                </form>
            </div>
    </x-member-page>
</x-app-layout>
