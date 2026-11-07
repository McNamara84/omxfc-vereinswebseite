<section class="rounded-xl border border-base-300 p-4 my-4" data-testid="inventory-stock">
    <h2 class="font-semibold text-xl">Aktueller Bestand</h2>
    <p class="my-2">Spielgeld: <strong>{{ $state['confirmed'] ? number_format($state['bucks'], 0, ',', '.').' Bucks' : 'Noch nicht bestätigt' }}</strong></p>
    @if(! $state['confirmed'])<p class="alert my-3">Geld und Munition dieses Bestandscharakters sind noch nicht bestätigt. Angezeigte Munitionsmengen sind Vorschläge aus den bisherigen Angaben. Die Leitung muss tatsächliche Mengen vor der ersten Buchung übernehmen.</p>@endif
    <ul class="space-y-1">@forelse($state['stocks'] as $id => $quantity)<li>{{ $quantity }} × {{ $catalog[$id]['name'] ?? $id }}</li>@empty<li>Keine Gegenstände oder Munition erfasst.</li>@endforelse</ul>
    <p class="mt-3">Aktive Kleidung: {{ $catalog['clothing:'.$state['clothing']]['name'] ?? 'Keine' }} · Rüstung: {{ $catalog['item:'.$state['armor']]['name'] ?? 'Keine' }} · Schild: {{ $catalog['item:'.$state['shield']]['name'] ?? 'Keiner' }}</p>
    @if($state['notes'] !== '')<p class="whitespace-pre-wrap mt-2">{{ $state['notes'] }}</p>@endif
    @php($education = collect($character->payload['skills'] ?? [])->firstWhere('name', 'Bildung')['value'] ?? 0)
    @foreach($state['stocks'] as $id => $quantity)
        @if(($catalog[$id]['minimumEducation'] ?? 0) > $education)<p class="text-sm mt-2">{{ $catalog[$id]['name'] }}: Mindestbildung {{ $catalog[$id]['minimumEducation'] }}; Bildung {{ $education }}. Besitz ist erlaubt, für die Benutzung gelten die Proben und Abzüge des Regelwerks.</p>@endif
    @endforeach
    @if(in_array('Primitiv', $character->payload['disadvantages'] ?? [], true))<p class="text-sm mt-2">Primitiv: Technische Gerätschaften können nicht benutzt werden.</p>@endif
</section>
