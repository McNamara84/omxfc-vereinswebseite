<x-app-layout>
    <x-member-page class="max-w-4xl">
        @include('romantausch.partials.offer-form', [
            'heading' => 'Neues Angebot erstellen',
            'submitLabel' => 'Angebot speichern',
            'formAction' => route('romantausch.store-offer'),
            'formMethod' => 'POST',
            'books' => $books,
            'types' => $types,
            'offer' => null,
        ])
    </x-member-page>
</x-app-layout>
