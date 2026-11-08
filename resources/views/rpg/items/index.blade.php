<x-member-layout>
    <x-member-page class="max-w-5xl">
        <x-ui.page-header title="Gegenstände verwalten" eyebrow="AG Rollenspiel" description="Eigene Ausrüstung nach dem Regelwerk entwerfen und als Startausrüstung freigeben." />
        <div class="flex flex-wrap gap-3 my-4"><a class="btn btn-primary" href="{{ route('rpg.items.create') }}">Neuen Gegenstand entwerfen</a><a class="btn btn-outline" href="{{ route('rpg.inventory.index') }}">Ausrüstung vergeben</a></div>
        @forelse($types as $type)
            @php($version = $type->versions->firstWhere('version', $type->current_version))
            <div class="flex flex-wrap gap-3 justify-between border-b border-base-300 py-4">
                <span>{{ $version->definition['name'] }} · Version {{ $type->current_version }} · {{ $type->archived ? 'Archiviert' : ($type->starting_equipment ? 'Als Startausrüstung freigegeben' : 'Nur spätere Vergabe') }}</span>
                <a class="btn btn-outline" href="{{ route('rpg.items.show', $type) }}">Anzeigen</a>
            </div>
        @empty<p>Noch keine eigenen Gegenstände erstellt.</p>@endforelse
        {{ $types->links() }}
    </x-member-page>
</x-member-layout>
