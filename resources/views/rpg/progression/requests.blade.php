<x-member-layout>
    <x-member-page class="max-w-5xl">
        <x-ui.page-header title="Verbesserungen prüfen" eyebrow="AG Rollenspiel" description="Genehmige nur Änderungen, die aus dem Abenteuer heraus nachvollziehbar sind." />
        <a class="btn btn-ghost my-4" href="{{ route('rpg.adventures.index') }}">Abenteuer und EP</a>
        <ul class="space-y-3">
            @forelse($requests as $advancement)
                <li class="border border-base-300 rounded-lg p-4">
                    <a class="link font-semibold" href="{{ route('rpg.advancements.show', $advancement) }}">{{ $advancement->character->displayName() }} · {{ $advancement->cost }} EP</a>
                    <p>{{ $advancement->character->user->nicknameOrName() }} · {{ $advancement->created_at->format('d.m.Y H:i') }}</p>
                </li>
            @empty
                <li>Keine offenen Anträge.</li>
            @endforelse
        </ul>
        {{ $requests->links() }}
    </x-member-page>
</x-member-layout>
