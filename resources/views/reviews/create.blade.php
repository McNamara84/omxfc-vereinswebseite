<x-app-layout :title="$title" :description="$description">
    <x-member-page class="max-w-3xl">
        <x-header separator>
            <x-slot:title>Neue Rezension zu „{{ $book->title }}“ (Nr. {{ $book->roman_number }})</x-slot:title>
        </x-header>

        <x-alert title="Hinweis" description="Du kannst die Rezensionen zu diesem Roman erst lesen, nachdem du selbst eine verfasst und gespeichert hast." icon="o-exclamation-triangle" class="alert-warning mb-6" />

        <x-card>
            <form action="{{ route('reviews.store', $book) }}" method="POST">
                @csrf

                <div class="space-y-4">
                    <x-input
                        name="title"
                        label="Rezensionstitel"
                        value="{{ old('title') }}"
                        required
                    />

                    <x-textarea
                        name="content"
                        label="Rezensionstext"
                        rows="8"
                        hint="Mindestens 140 Zeichen."
                        required
                    >{{ old('content') }}</x-textarea>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <x-button label="Abbrechen" link="{{ route('reviews.index') }}" class="btn-ghost" />
                    <x-button label="Rezension absenden" type="submit" class="btn-primary" icon="o-check" />
                </div>
            </form>
        </x-card>
    </x-member-page>
</x-app-layout>