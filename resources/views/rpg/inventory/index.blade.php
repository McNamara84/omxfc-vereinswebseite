<x-member-layout>
    <x-member-page class="max-w-5xl">
        <x-ui.page-header title="Ausrüstung verwalten" eyebrow="AG Rollenspiel" description="Inventar, Munition, Spielgeld und Handel der Charaktere verwalten." />
        <a class="btn btn-ghost my-3" href="{{ route('rpg.characters.index') }}">Meine Charaktere</a>
        <form method="GET" class="flex flex-wrap gap-3 my-4"><label class="fieldset">Charakter oder Besitzer suchen<input class="input" type="search" name="search" maxlength="100" value="{{ $search }}" /></label><button class="btn self-end">Suchen</button></form>
        <div class="divide-y divide-base-300">
            @forelse($characters as $character)
                <div class="flex flex-wrap items-center justify-between gap-3 py-4"><span>{{ $character->displayName() }} · {{ $character->user->nicknameOrName() }}</span><a class="btn btn-primary" href="{{ route('rpg.inventory.show', $character) }}">Ausrüstung verwalten</a></div>
            @empty<p>Keine passenden Charaktere vorhanden.</p>@endforelse
        </div>
        {{ $characters->links() }}
    </x-member-page>
</x-member-layout>
