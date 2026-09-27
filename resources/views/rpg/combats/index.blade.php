<x-member-layout>
    <x-member-page class="max-w-5xl">
        <x-ui.page-header title="Übungskämpfe" eyebrow="AG Rollenspiel" description="Teste deine Charaktere in einem asynchronen Duell." />
        <div class="flex flex-wrap gap-3 my-4"><a class="btn btn-primary" href="{{ route('rpg.combats.create') }}">Herausfordern</a><a class="btn btn-outline" href="{{ route('rpg.characters.index') }}">Meine Charaktere</a></div>
        <p class="mb-4">Sichtbar für die Beteiligten und die aktuelle AG-Leitung. Öffentliche Meilensteine erscheinen im Dashboard.</p>
        <div class="space-y-3">
            @forelse($combats as $combat)
                <article class="border border-base-300 rounded-xl p-4"><h2 class="font-semibold"><a class="link" href="{{ route('rpg.combats.show', $combat) }}">{{ $combat->participants->pluck('character_name')->join(' gegen ') }}</a></h2><p>{{ \App\Models\RpgCombat::statusLabel($combat->status) }} · {{ $combat->created_at->timezone('Europe/Berlin')->format('d.m.Y') }}</p></article>
            @empty<p>Noch keine Übungskämpfe vorhanden.</p>@endforelse
        </div>
        <div class="mt-4">{{ $combats->links() }}</div>
    </x-member-page>
</x-member-layout>
