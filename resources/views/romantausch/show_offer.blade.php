<x-app-layout>
    <x-member-page class="max-w-4xl">
        <x-card shadow>
            <x-header title="Angebotsdetails" separator />

            <p class="mb-4">{{ $offer->series }} {{ $offer->book_number }} - {{ $offer->book_title }} ({{ $offer->condition }})</p>

            @if(!empty($offer->photos))
                <div class="flex flex-wrap gap-4 mb-4">
                    @foreach($offer->photos as $photo)
                        <img src="{{ asset('storage/' . $photo) }}" alt="Angebotsfoto" class="w-32 h-auto rounded">
                    @endforeach
                </div>
            @endif

            <x-button label="Zurück zur Übersicht" link="{{ route('romantausch.index') }}" icon="o-arrow-left" class="btn-ghost btn-sm" />
        </x-card>
    </x-member-page>
</x-app-layout>
