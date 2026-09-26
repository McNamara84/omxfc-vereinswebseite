<p class="my-4 font-semibold" data-testid="experience-summary">Erhalten: {{ $history['received'] }} EP · Ausgegeben: {{ $history['spent'] }} EP · Verfügbar: {{ $history['balance'] }} EP</p>
@forelse($history['entries'] as $entry)
    <article class="my-4 rounded-lg border border-base-300 p-4">
        <h3 class="font-semibold">{{ $entry['title'] }} · {{ $entry['amount'] >= 0 ? '+' : '' }}{{ $entry['amount'] }} EP</h3>
        <p class="text-sm">{{ $entry['date'] }} · {{ $entry['actor'] }} · Kontostand danach: {{ $entry['balance'] }} EP</p>
        @if($entry['criteria'] !== null)
            <p>Abschluss: {{ $entry['completed_on'] }} · Spielzeit: {{ $entry['minutes'] }} Minuten · Zyklusbonus: {{ $entry['cycle_bonus'] }} EP</p>
            <p>Überlebt: {{ $entry['criteria']['survived'] }} · Rollenspiel: {{ $entry['criteria']['roleplay'] }} · Humor: {{ $entry['criteria']['humor'] }} · Rettung: {{ $entry['criteria']['rescue'] }} · Unverwundet: {{ $entry['criteria']['unwounded'] }}</p>
            <p>Regelwert: {{ $entry['calculated_points'] }} EP</p>
        @endif
        @if($entry['reason'])<p class="whitespace-pre-wrap break-words">{{ $entry['reason'] }}</p>@endif
        @include('rpg.progression.partials.changes', ['changes' => $entry['changes']])
    </article>
@empty
    <p>Noch keine Erfahrungspunkte gebucht.</p>
@endforelse
