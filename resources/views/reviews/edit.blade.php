<x-app-layout :title="$title" :description="$description">
    <x-member-page class="max-w-3xl">
        <x-header separator>
            <x-slot:title>Rezension zu „{{ $review->book->title }}“ bearbeiten</x-slot:title>
        </x-header>

        <x-card>
            <form action="{{ route('reviews.update', $review) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="space-y-4">
                    <x-input
                        name="title"
                        label="Rezensionstitel"
                        value="{{ old('title', $review->title) }}"
                        required
                    />

                    <x-textarea
                        name="content"
                        label="Rezensionstext"
                        rows="8"
                        hint="Mindestens 140 Zeichen."
                        required
                    >{{ old('content', $review->content) }}</x-textarea>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <x-button label="Abbrechen" link="{{ route('reviews.show', $review->book) }}" class="btn-ghost" />
                    <x-button label="Rezension aktualisieren" type="submit" class="btn-primary" icon="o-check" />
                </div>
            </form>
        </x-card>
    </x-member-page>
</x-app-layout>