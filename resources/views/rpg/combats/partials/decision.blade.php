@php($acting = $actors[$decision['side']] ?? null)
<form method="POST" action="{{ route('rpg.combats.decide', [$combat, $decision['id']]) }}" class="space-y-3" x-data="{ kind: 'attack', power: 'Pyrokinese' }">
    @csrf
    @if($decision['type'] === 'ruling')
        @include('rpg.combats.partials.ruling-request')
        @foreach($decision['context']['rules'] as $key)
            <label class="block">{{ $rulings[$key]['label'] }} (S. {{ $rulings[$key]['pages'] }})
                <select class="select w-full" name="choices[{{ $key }}]">@foreach($rulings[$key]['options'] as $value => $label)<option value="{{ $value }}">{{ $label }}{{ $loop->first ? ' (Timeout-Standard)' : '' }}</option>@endforeach</select>
            </label>
        @endforeach
        <label class="block">Begründung<textarea name="reason" class="textarea w-full" required minlength="3" maxlength="2000"></textarea></label>
    @elseif($decision['type'] === 'prepare')
        @include('rpg.combats.partials.loadout')
        <label class="block">Initiativefertigkeit<select name="skill" class="select"><option>Nahkampf</option><option>Fernkampf</option><option>Feuerwaffen</option><option>Psychisch</option></select></label>
        <p class="text-sm">Die Initiativefertigkeit muss zur gewählten Ausrüstung passen. Ohne Waffe gilt Nahkampf. Psychische Vorbereitung setzt ein nutzbares Talent voraus.</p>
    @elseif($decision['type'] === 'defense')
        <label class="block">Verteidigung<select name="defense" class="select"><option value="dodge">Ausweichen (Athletik + GE)</option><option value="parry">Parade (Nahkampf + GE, nur Nahkampfangriffe, einmal pro Runde)</option></select></label>
        <label class="block">Volle Verteidigung<select name="full_defense" class="select"><option value="0">Bisherige Haltung beibehalten</option><option value="1">Volle Verteidigung (+2, kein eigener Angriff)</option></select></label>
    @elseif($decision['type'] === 'action')
        <label class="block">Handlung<select class="select w-full" name="kind" x-model="kind">@foreach(\App\Services\RpgCombat\CombatEngine::actionLabels() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
        <label class="block">Bewegung in Zentimetern (mit Vorzeichen)<input class="input w-full" type="number" name="move" value="0" step="1" required></label>
        <p class="text-sm">Positive Werte bewegen nach rechts, negative nach links. Normale Bewegung: {{ \App\Services\RpgCombat\CombatStats::movement($acting) / 100 }} m, Rennen vierfach. Nahkampfreichweite: 1 m. Eine Bewegung ersetzt beim Rennen den Angriff.</p>
        <fieldset x-show="['attack','disarm','knockdown','object','reload','unjam','pickup'].includes(kind)" :disabled="!['attack','disarm','knockdown','object','reload','unjam','pickup'].includes(kind)" class="space-y-3">
            <label class="block">Waffe<select name="weapon" class="select w-full">@foreach($acting['weapons'] as $weapon)<option value="{{ $weapon['instance'] }}">{{ $weapon['name'] }} ({{ $weapon['instance'] }})</option>@endforeach</select></label>
        </fieldset>
        <fieldset x-show="['attack','disarm','knockdown','object'].includes(kind)" :disabled="!['attack','disarm','knockdown','object'].includes(kind)" class="space-y-3">
            <label class="block">Waffenmodus (siehe Kampfwerte)<input class="input" type="number" min="0" max="5" name="mode" value="0" required></label>
            <label class="block">Gezielter Schlag: Schadensbonus<input class="input" type="number" min="0" max="50" name="aim" value="0" required></label>
            <p class="text-sm">Je +1 Schaden kostet −2 Angriff. Der verbleibende Angriffsmodifikator muss mindestens −1 sein. Entwaffnen und Niederwerfen verwenden 0.</p>
            <label class="block">Feuerart<select class="select" name="fire"><option value="E">E – Einzelschuss (1)</option><option value="H">H – Halbautomatisch (2 / −1)</option><option value="S">S – Salve (6 / −2)</option><option value="A">A – Automatisch (15 / −4 / S +1)</option></select></label>
            <p class="text-sm">Das günstigere für den Waffenmodus erlaubte Attribut wird verwendet. Fernkampf nutzt WA; Schadenswürfe nutzen ST im Nahkampf und WA im Fernkampf.</p>
        </fieldset>
        <fieldset x-show="kind === 'object'" :disabled="kind !== 'object'"><label>Objekt<select class="select" name="target"><option value="shield">Schild</option><option value="armor">Rüstung</option></select></label></fieldset>
        <fieldset x-show="kind === 'switch'" :disabled="kind !== 'switch'">@include('rpg.combats.partials.loadout')</fieldset>
        <fieldset x-show="kind === 'creative'" :disabled="kind !== 'creative'"><label class="block">Handlung und relevanten Eigenschaftsauslöser beschreiben<textarea class="textarea w-full" name="description" minlength="3" maxlength="1000" required></textarea></label></fieldset>
        <fieldset x-show="kind === 'psychic'" :disabled="kind !== 'psychic'" class="space-y-3">
            <label class="block">Talent<select class="select" name="power" x-model="power">@foreach($acting['profile']['psychic']['powers'] as $talent)@if($talent['usable'])<option>{{ $talent['name'] }}</option>@endif @endforeach</select></label>
            <div class="grid sm:grid-cols-3 gap-2">@foreach(['duration'=>'Dauer [D]', 'range'=>'Reichweite [R]', 'strength'=>'Stärke [S]'] as $key => $label)<label>{{ $label }}<input class="input w-full" type="number" name="{{ $key }}" min="0" max="12" value="0" required></label>@endforeach</div>
            <p class="text-sm">Summe der Parameter höchstens 2 × Talentfertigkeit. Eingaben sind Parameterpunkte, keine Meter oder Sekunden. Tabelle: R = Berührung / 2 / 10 / 50 / 250 / 1.000 m; D = sofort / 10 s / 60 s / 6 min / 30 min / 3 h; S = 1 / 5 / 10 / 20 / 40 / 80. Weitere Stufen: R ×5, D ×6, S ×2.</p>
            <fieldset x-show="power === 'Pyrokinese'" :disabled="power !== 'Pyrokinese'"><label>Schadensbonus<input class="input" type="number" name="damage" min="0" value="0" required></label><p class="text-sm">D ist hier die Zahl der Brennrunden, mindestens 1. Kosten: (Parametersumme + Schaden + 2) × 3 PEP.</p></fieldset>
            <fieldset x-show="power === 'Telekinese'" :disabled="power !== 'Telekinese'" class="space-y-2">
                <label>Wirkung<select class="select" name="effect"><option value="projectile">Geschoss (D = 3, Schaden [S] − 3)</option><option value="move">Gegner bewegen (Masse: Leitungsentscheidung)</option></select></label>
                <label class="block">Verschiebung des Ziels in cm<input class="input" type="number" name="displacement" value="0" required></label>
            </fieldset>
            <fieldset x-show="power === 'Telepathie'" :disabled="power !== 'Telepathie'"><label>Wirkung<select class="select" name="effect"><option value="read">Gedanken lesen</option><option value="send">Gedanken senden</option></select></label></fieldset>
        </fieldset>
    @else
        <p>Der Server würfelt die benötigten Würfel und protokolliert jeden Modifikator. Das Ergebnis ist verbindlich.</p>
    @endif
    <button class="btn btn-primary" type="submit">{{ in_array($decision['type'], ['initiative','damage','fumble','strength','resistance','psychic_resistance','technology'], true) ? 'Jetzt würfeln' : 'Entscheidung bestätigen' }}</button>
    @if($decision['type'] === 'ruling')
        <button class="btn btn-outline" type="submit" name="abort" value="1">Mit Begründung neutral abbrechen</button>
        <p class="text-sm">Wenn sich der Fall mit den angebotenen Auslegungen nicht abbilden lässt, kann die unbeteiligte AG-Leitung das Duell ohne Sieger beenden.</p>
    @endif
</form>
