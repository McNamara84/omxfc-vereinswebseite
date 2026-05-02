<x-member-page class="max-w-6xl space-y-8">
    <x-ui.page-header
        title="Angebotsdetails"
        eyebrow="Romantauschbörse"
        :description="$this->offer->series . ' ' . $this->offer->book_number . ' - ' . $this->offer->book_title . ' (' . $this->offer->condition . ')'"
        data-testid="page-title"
    >
        <x-slot:actions>
            <x-button label="Zurück zur Übersicht" link="{{ route('romantausch.index') }}" wire:navigate icon="o-arrow-left" class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-ui.page-header>

    <section class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(18rem,0.72fr)] xl:items-start">
        <x-ui.panel title="Angebot" description="Band, Zustand und gegebenenfalls Fotos sind hier kompakt zusammengefasst.">
            <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/78 px-4 py-4 text-sm leading-relaxed text-base-content/78 sm:text-base">
                {{ $this->offer->series }} {{ $this->offer->book_number }} - {{ $this->offer->book_title }} ({{ $this->offer->condition }})
            </div>

            @if(!empty($this->offer->photos))
                <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($this->offer->photos as $photo)
                        <div class="overflow-hidden rounded-[1.25rem] border border-base-content/10 bg-base-100/78 p-2">
                            <img src="{{ Storage::disk('public')->url($photo) }}" alt="Angebotsfoto" class="h-48 w-full rounded-xl object-cover">
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.panel>

        <x-ui.panel title="Nächster Schritt" description="Von hier aus kannst du zurück in die Übersicht springen und dort weitere Angebote, Gesuche oder Matches prüfen.">
            <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-4 text-sm leading-relaxed text-base-content/76 sm:text-base">
                Wenn ein Tausch zustande kommt, erfolgt die eigentliche Abstimmung und Bestätigung wieder über die Romantausch-Übersicht.
            </div>
        </x-ui.panel>
    </section>
</x-member-page>
