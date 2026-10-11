<x-member-layout>
    <x-member-page class="max-w-3xl">
        <x-ui.page-header title="Zum Übungskampf herausfordern" eyebrow="AG Rollenspiel" />
        <a class="btn btn-ghost my-3" href="{{ route('rpg.combats.index') }}">Alle Kämpfe</a>
        @include('rpg.combats.partials.errors')
        @can('manage-rpg-npcs')
            <x-ui.panel title="Mit NSC herausfordern" class="mb-6">
                <form method="POST" action="{{ route('rpg.combats.store') }}" class="space-y-4" x-data="{ npc: '{{ $selectedNpc ?: '' }}', opponent: '' }">
                    @csrf
                    <input type="hidden" name="kind" value="npc_vs_player">
                    <input type="hidden" name="submission_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                    <label class="block">NSC der AG<select class="select w-full" name="npc_id" x-model="npc" required>@if(!$selectedNpc)<option value="">Bitte wählen</option>@endif @foreach($npcs as $npc)<option value="{{ $npc->id }}" @selected($npc->id === $selectedNpc)>{{ $npc->displayName() }}</option>@endforeach</select></label>
                    <label class="block">Herausgeforderter Charakter<select class="select w-full" name="opponent_id" x-model="opponent" required><option value="">Bitte wählen</option>@foreach($characters->where('user_id', '!=', auth()->id()) as $character)<option value="{{ $character->id }}">{{ $character->displayName() }} ({{ $character->user->nicknameOrName() }})</option>@endforeach</select></label>
                    @foreach($npcs as $npc)<input type="hidden" name="npc_revision" value="{{ $npc->revision }}" :disabled="npc !== '{{ $npc->id }}'">@endforeach
                    @foreach($characters->where('user_id', '!=', auth()->id()) as $character)<input type="hidden" name="opponent_revision" value="{{ $character->revision }}" :disabled="opponent !== '{{ $character->id }}'">@endforeach
                    <label class="block">Startentfernung in Metern<input type="number" name="distance" class="input w-full" min="1" max="{{ config('rpg-combat.maximum_start_distance') }}" value="{{ config('rpg-combat.default_distance') }}" required></label>
                    @include('rpg.combats.partials.conditions', ['npcCombat' => true])
                    <button type="submit" class="btn btn-primary">Mit NSC verbindlich herausfordern</button>
                </form>
            </x-ui.panel>
        @endcan
        <form method="POST" action="{{ route('rpg.combats.store') }}" class="space-y-5" x-data="{ own: '', opponent: '' }">
            @csrf
            <input type="hidden" name="submission_key" value="{{ $submissionKey }}">
            <label class="block">Dein Charakter<select name="character_id" required class="select w-full" x-model="own"><option value="">Bitte wählen</option>
                @foreach($characters->where('user_id', auth()->id()) as $character)<option value="{{ $character->id }}">{{ $character->displayName() }}</option>@endforeach
            </select></label>
            <label class="block">Herausgeforderter Charakter<select name="opponent_id" required class="select w-full" x-model="opponent"><option value="">Bitte wählen</option>
                @foreach($characters->where('user_id', '!=', auth()->id()) as $character)<option value="{{ $character->id }}">{{ $character->displayName() }} ({{ $character->user->nicknameOrName() }})</option>@endforeach
            </select></label>
            @foreach($characters as $character)
                <input type="hidden" name="{{ $character->user_id === auth()->id() ? 'revision' : 'opponent_revision' }}" value="{{ $character->revision }}" :disabled="{{ $character->user_id === auth()->id() ? 'own' : 'opponent' }} !== '{{ $character->id }}'">
            @endforeach
            <label class="block">Startentfernung in Metern<input class="input w-full" type="number" name="distance" min="1" max="{{ config('rpg-combat.maximum_start_distance') }}" value="{{ config('rpg-combat.default_distance') }}" required></label>
            @include('rpg.combats.partials.conditions')
            <button class="btn btn-primary" type="submit">Verbindlich herausfordern</button>
        </form>
    </x-member-page>
</x-member-layout>
