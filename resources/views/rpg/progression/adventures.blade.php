<x-member-layout>
    <x-member-page class="max-w-5xl">
        <x-ui.page-header title="Abenteuer und Erfahrungspunkte" eyebrow="AG Rollenspiel" description="Abgeschlossene Vergaben bleiben mit ihrer ursprünglichen Bewertung dokumentiert." />
        <div class="flex flex-wrap gap-3 my-4"><a class="btn btn-primary" href="{{ route('rpg.adventures.create') }}">EP vergeben</a><a class="btn" href="{{ route('rpg.advancements.index') }}">Verbesserungen prüfen</a><a class="btn btn-ghost" href="{{ route('rpg.characters.index') }}">Meine Charaktere</a></div>
        @include('rpg.progression.partials.messages')
        <ul class="space-y-3">
            @forelse($adventures as $adventure)
                <li class="border border-base-300 rounded-lg p-4"><a class="link font-semibold" href="{{ route('rpg.adventures.show', $adventure) }}">{{ $adventure->title }}</a><p>{{ $adventure->completed_on->format('d.m.Y') }} · {{ $adventure->awards_count }} Charaktere</p></li>
            @empty
                <li>Noch keine Abenteuer erfasst.</li>
            @endforelse
        </ul>
        {{ $adventures->links() }}
    </x-member-page>
</x-member-layout>
