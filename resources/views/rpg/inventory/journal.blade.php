<section class="my-5" data-testid="inventory-journal">
    <h2 class="font-semibold text-xl my-3">Inventarverlauf</h2>
    @forelse($entries as $entry)
        @php($transaction = $entry->transaction)
        <article class="border-b border-base-300 py-4 space-y-2">
            <h3 class="font-semibold">#{{ $transaction->id }} · {{ $transaction->label() }} · {{ $transaction->occurred_on->format('d.m.Y') }}</h3>
            <p class="text-sm">Gebucht {{ $transaction->created_at->timezone('Europe/Berlin')->format('d.m.Y H:i') }} · {{ $transaction->actor?->nicknameOrName() ?? 'Ehemaliges Mitglied' }}</p>
            <p class="whitespace-pre-wrap">{{ $transaction->reason }}</p>
            @foreach($entry->changes as $change)<p>{{ $change['name'] }}: {{ is_bool($change['before']) ? ($change['before'] ? 'Ja' : 'Nein') : ($change['before'] ?? 'Unbekannt') }} → {{ is_bool($change['after']) ? ($change['after'] ? 'Ja' : 'Nein') : ($change['after'] ?? 'Unbekannt') }}</p>@endforeach
            @if(! empty($transaction->details['external_party']))<p>Gegenpartei: {{ $transaction->details['external_party'] }}</p>@endif
            @if(! empty($transaction->details['price']))<p>Vereinbarter Preis: {{ $transaction->details['price']['agreed'] }} Bucks · {{ $transaction->details['price_reason'] }}</p>@endif
            @if($transaction->reversal_of)<p>Rückbuchung zu #{{ $transaction->reversal_of }}.</p>@endif
            @if($transaction->reversal)<p>Zurückgebucht durch #{{ $transaction->reversal->id }}.</p>@endif
            @if($canManage && ! $transaction->reversal && ! in_array($transaction->kind, ['initialize','advancement','reversal'], true))
                <button type="button" class="btn btn-ghost btn-sm" @click="beginReversal({{ $transaction->id }}, @js($transaction->entries->firstWhere('rpg_character_id', '!=', $character->id)?->rpg_character_id))">Vorgang #{{ $transaction->id }} zurückbuchen</button>
            @endif
        </article>
    @empty<p>Noch keine Inventarbuchungen.</p>@endforelse
    {{ $entries->links() }}
</section>
