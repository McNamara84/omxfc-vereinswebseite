<x-member-layout>
    <x-member-page class="max-w-3xl">
        <x-ui.page-header title="Zum Übungskampf herausfordern" eyebrow="AG Rollenspiel" />
        <a class="btn btn-ghost my-3" href="{{ route('rpg.combats.index') }}">Alle Kämpfe</a>
        @include('rpg.combats.partials.errors')
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
