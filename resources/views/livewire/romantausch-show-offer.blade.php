<x-member-page class="max-w-4xl">
    <x-card shadow>
        <x-header title="Angebotsdetails" separator useH1 data-testid="page-title" />

        <p class="mb-4">{{ $this->offer->series }} {{ $this->offer->book_number }} - {{ $this->offer->book_title }} ({{ $this->offer->condition }})</p>

        @if(!empty($this->offer->photos))
            <div class="flex flex-wrap gap-4 mb-4">
                @foreach($this->offer->photos as $photo)
                    <img src="{{ asset('storage/'.$photo) }}" alt="Angebotsfoto" class="w-32 h-auto rounded">
                @endforeach
            </div>
        @endif

        <x-button label="Zurück zur Übersicht" link="{{ route('romantausch.index') }}" wire:navigate icon="o-arrow-left" class="btn-ghost btn-sm" />
    </x-card>
</x-member-page>
