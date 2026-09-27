@php
    $request = $decision['context']['request'] ?? [];
    $selection = $request['selection'] ?? [];
    $requestActor = $actors[$request['side'] ?? 0] ?? null;
    $fieldLabels = ['kind' => 'Handlung', 'description' => 'Beschreibung', 'skill' => 'Initiativefertigkeit', 'weapons' => 'Geführte Waffen',
        'shield' => 'Schild verwenden', 'weapon' => 'Waffe', 'mode' => 'Waffenmodus', 'aim' => 'Gezielter Schadensbonus', 'move' => 'Bewegung in cm',
        'fire' => 'Feuerart', 'defense' => 'Verteidigung', 'full_defense' => 'Volle Verteidigung', 'power' => 'Talent',
        'duration' => 'Dauer [D]', 'range' => 'Reichweite [R]', 'strength' => 'Stärke [S]', 'damage' => 'Schadensbonus',
        'effect' => 'Wirkung', 'displacement' => 'Verschiebung in cm', 'target' => 'Ziel'];
@endphp
<p>Regelfall zu {{ $requestActor['profile']['name'] ?? 'beiden Charakteren' }}. Die bisherigen Würfe und Modifikatoren stehen im Kampfprotokoll.</p>
<dl class="space-y-1">
    @foreach($selection as $key => $value)
        @if(isset($fieldLabels[$key]) && (is_scalar($value) || ($key === 'weapons' && is_array($value) && array_all($value, fn ($id) => is_string($id)))))
            @php
                $display = match ($key) {
                    'kind' => \App\Services\RpgCombat\CombatEngine::actionLabels()[$value] ?? $value,
                    'weapon' => $requestActor['weapons'][$value]['name'] ?? $value,
                    'weapons' => collect($value)->map(fn ($id) => $requestActor['weapons'][$id]['name'] ?? $id)->join(', ') ?: 'unbewaffnet',
                    'effect' => ['read' => 'Gedanken lesen', 'send' => 'Gedanken senden', 'move' => 'Gegner bewegen', 'projectile' => 'Geschoss'][$value] ?? $value,
                    'defense' => $value === 'parry' ? 'Parade' : 'Ausweichen',
                    'target' => $value === 'armor' ? 'Rüstung' : 'Schild',
                    default => is_bool($value) ? ($value ? 'Ja' : 'Nein') : $value,
                };
            @endphp
            <div><dt class="font-semibold">{{ $fieldLabels[$key] }}</dt><dd>{{ $display }}</dd></div>
        @endif
    @endforeach
</dl>
