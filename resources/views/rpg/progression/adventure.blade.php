<x-member-layout>
    <x-member-page class="max-w-5xl">
        <x-ui.page-header :title="$adventure->title" eyebrow="Abgeschlossene Vergabe" description="Die Bewertung und vergebenen EP dieses Abenteuers." />
        <a class="btn btn-ghost my-3" href="{{ route('rpg.adventures.index') }}">Vergabeübersicht</a>
        @include('rpg.progression.partials.messages')
        <p>Abschluss: {{ $adventure->completed_on->format('d.m.Y') }} · {{ $adventure->minutes }} Minuten · Zyklusbonus: {{ $adventure->cycle_bonus }} EP</p>
        <p>Vergeben von {{ $adventure->user?->nicknameOrName() ?? 'Ehemaliges Mitglied' }} am {{ $adventure->created_at->format('d.m.Y H:i') }}.</p>
        @foreach($adventure->awards as $award)
            <article class="my-4 border border-base-300 rounded-lg p-4 space-y-2">
                <h2 class="font-semibold">{{ $award->character_name }} · {{ $award->points }} EP (Regelwert: {{ $award->calculated_points }} EP)</h2>
                <p>Überlebt: {{ $award->criteria['survived'] }} · Rollenspiel: {{ $award->criteria['roleplay'] }} · Humor: {{ $award->criteria['humor'] }} · Rettung: {{ $award->criteria['rescue'] }} · Unverwundet: {{ $award->criteria['unwounded'] }}</p>
                @if($award->reason)<p class="whitespace-pre-wrap">{{ $award->reason }}</p>@endif
                @if($award->character)<a class="link" href="{{ route('rpg.characters.history', $award->character) }}">Charakterverlauf und Herkunft</a>@endif
            </article>
        @endforeach
    </x-member-page>
</x-member-layout>
