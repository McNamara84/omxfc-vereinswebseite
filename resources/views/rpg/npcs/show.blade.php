<x-member-layout>
    <x-member-page class="max-w-4xl">
        <x-ui.page-header :title="$npc->displayName()" eyebrow="NSC der AG Rollenspiel" />
        <a class="btn btn-ghost my-3" href="{{ route('rpg.characters.index') }}">Meine Charaktere und NSCs</a>
        @include('rpg.combats.partials.errors')
        @if(session('success'))<p class="alert alert-success">{{ session('success') }}</p>@endif
        <x-ui.panel title="Vorgegebener Charakterstand">@include('rpg.npcs.partials.profile')</x-ui.panel>
        <a href="{{ route('rpg.combats.create', ['npc_id' => $npc->id]) }}" class="btn btn-primary my-4">Zum Übungskampf herausfordern</a>
        @can('update', $npc)
            <form method="POST" action="{{ route('rpg.npcs.rename', $npc) }}" class="space-y-3 mb-6">@csrf @method('PATCH')
                <input type="hidden" name="revision" value="{{ $npc->revision }}">
                <label class="block">Optionaler Name<input class="input w-full" name="custom_name" maxlength="255" value="{{ $npc->custom_name }}"></label>
                <button class="btn btn-outline" type="submit">Name speichern</button>
            </form>
        @endcan
        <form method="POST" action="{{ route('rpg.npcs.destroy', $npc) }}">@csrf @method('DELETE')
            <button type="submit" class="btn btn-outline text-error" onclick="return confirm('Diesen NSC wirklich löschen?');">NSC löschen</button>
        </form>
    </x-member-page>
</x-member-layout>
