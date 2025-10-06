<x-app-layout>
    <x-member-page class="max-w-4xl">
        @include('romantausch.partials.request-form', [
            'heading' => 'Gesuch bearbeiten',
            'submitLabel' => 'Änderungen speichern',
            'formAction' => route('romantausch.update-request', $requestModel),
            'formMethod' => 'PUT',
            'books' => $books,
            'types' => $types,
            'requestModel' => $requestModel,
        ])
    </x-member-page>
</x-app-layout>
