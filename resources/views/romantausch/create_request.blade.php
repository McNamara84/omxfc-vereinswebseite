<x-app-layout>
    <x-member-page class="max-w-4xl">
        @include('romantausch.partials.request-form', [
            'heading' => 'Neues Gesuch erstellen',
            'submitLabel' => 'Gesuch speichern',
            'formAction' => route('romantausch.store-request'),
            'formMethod' => 'POST',
            'books' => $books,
            'types' => $types,
            'requestModel' => null,
        ])
    </x-member-page>
</x-app-layout>
