<x-app-layout>
    <x-member-page class="max-w-4xl">
        @include('romantausch.partials.offer-form', [
            'heading' => 'Angebot bearbeiten',
            'submitLabel' => 'Änderungen speichern',
            'formAction' => route('romantausch.update-offer', $offer),
            'formMethod' => 'PUT',
            'books' => $books,
            'types' => $types,
            'offer' => $offer,
        ])
    </x-member-page>
</x-app-layout>
