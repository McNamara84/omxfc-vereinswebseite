@php($current = $sheet['progression_payload'])
<div class="progression-pages">
    <h1>{{ $current['character']['character_name'] ?? 'Charakter' }} – Erfahrung und vollständige Angaben</h1>
    <p>Erhalten: {{ $sheet['experience']['received'] ?? 0 }} EP · Ausgegeben: {{ $sheet['experience']['spent'] ?? 0 }} EP · Verfügbar: {{ $sheet['experience']['balance'] ?? 0 }} EP</p>
    @if(! empty($current['character']['description']))<p>{{ $current['character']['description'] }}</p>@endif
    <h2>Aktuelle Fertigkeiten</h2>
    <table><thead><tr><th>Fertigkeit / Spezialisierung</th><th>Wert</th></tr></thead><tbody>
        @foreach($current['skills'] ?? [] as $skill)<tr><td>{{ $skill['name'] }}</td><td>{{ $skill['value'] }}</td></tr>@endforeach
    </tbody></table>
    @if(! empty($current['languages']))<p>Sprachen und Dialekte: {{ implode(', ', $current['languages']) }}</p>@endif
    <h2>Aktuelle Vorteile</h2>
    @foreach($current['advantage_effects'] ?? [] as $effect)
        <p><strong>{{ $effect['name'] }}@if(! empty($effect['target'])) · {{ $effect['target'] }}@endif:</strong> {{ $effect['justification'] ?? '' }}</p>
    @endforeach
    <h2>Aktuelle Nachteile</h2>
    @forelse($current['disadvantages'] ?? [] as $name)<p><strong>{{ $name }}:</strong> {{ $current['disadvantage_details'][$name] ?? '' }}</p>@empty<p>Keine</p>@endforelse
    @if(! empty($sheet['psychic']['powers']))
        <h2>Psychische Kräfte · {{ $sheet['psychic']['pep'] }} PEP</h2>
        @foreach($sheet['psychic']['powers'] as $power)<p>{{ $power['name'] }}: FW {{ $power['value'] }} · {{ $power['usable'] ? 'Einsetzbar' : ($power['value'] === 0 ? 'Latent (FW 0)' : 'Derzeit nicht einsetzbar (WI unter 0)') }}</p>@endforeach
    @endif
    <h2>Vollständige Ausrüstung</h2>
    @foreach($current['equipment']['items'] ?? [] as $item)<p>{{ $item['quantity'] ?? 1 }} × {{ $item['name'] ?? $item['id'] }}</p>@endforeach
    <p>{{ $current['equipment']['notes'] ?? '' }}</p>
    @if(! empty($current['progression']['metadata_history']))
        <h2>Ergänzte Herkunftsangaben</h2>
        @foreach($current['progression']['metadata_history'] as $metadata)
            <p>{{ $metadata['date'] }} · {{ $metadata['actor'] }}: {{ $metadata['reason'] }}</p>
            @if($metadata['barbar_attribute'])<p>Ursprünglicher Barbarenbonus: {{ strtoupper($metadata['barbar_attribute']) }}</p>@endif
            @foreach($metadata['targets'] as $index => $target)<p>Vorteilsinstanz {{ $index + 1 }}: {{ $target }}</p>@endforeach
        @endforeach
    @endif
</div>
<div class="progression-pages">
    <h1>{{ $current['character']['character_name'] ?? 'Charakter' }} – Erfahrungsverlauf</h1>
    @foreach($sheet['experience']['entries'] ?? [] as $entry)
        <h2>{{ $entry['date'] }} · {{ $entry['title'] }}</h2>
        <p>{{ $entry['amount'] >= 0 ? '+' : '' }}{{ $entry['amount'] }} EP · Kontostand danach: {{ $entry['balance'] }} EP</p>
        <p>{{ $entry['actor'] }} · Regelversion {{ $entry['rule_version'] }}</p>
        @if($entry['criteria'] !== null)
            <p>Abenteuerabschluss: {{ $entry['completed_on'] }} · Spielzeit: {{ $entry['minutes'] }} Minuten · Zyklusbonus: {{ $entry['cycle_bonus'] }} EP</p>
            <p>Überlebt: {{ $entry['criteria']['survived'] }} · Rollenspiel: {{ $entry['criteria']['roleplay'] }} · Humor: {{ $entry['criteria']['humor'] }} · Rettung: {{ $entry['criteria']['rescue'] }} · Unverwundet: {{ $entry['criteria']['unwounded'] }} · Regelwert: {{ $entry['calculated_points'] }} EP</p>
        @endif
        @if($entry['reason'])<p>Abweichung: {{ $entry['reason'] }}</p>@endif
        @if($entry['changes'])
            <table><thead><tr><th>Änderung</th><th>Vorher</th><th>Nachher</th><th>Teilkosten</th></tr></thead><tbody>
                @foreach($entry['changes'] as $change)<tr><td>{{ $change['name'] }} {{ $change['target'] }}</td><td>{{ $change['before'] }}</td><td>{{ $change['after'] }}</td><td>{{ $change['cost'] }} EP</td></tr>@endforeach
            </tbody></table>
            @foreach($entry['changes'] as $change)
                <h3>{{ $change['name'] }}: Begründung</h3><p>{{ $change['reason'] }}</p>
                @if(! empty($change['languages']))<p>Sprachen: {{ implode(', ', $change['languages']) }}</p>@endif
                @if(! empty($change['items']))<p>Gegenstände: {{ implode(', ', array_map(fn($id) => \App\Support\RpgCharEditorEquipment::itemMap()[$id]['name'] ?? $id, $change['items'])) }}</p>@endif
            @endforeach
        @endif
    @endforeach
</div>
