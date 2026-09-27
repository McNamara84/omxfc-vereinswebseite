<header class="space-y-2">
    <h2 class="text-2xl font-semibold">{{ $combat->participants->pluck('character_name')->join(' gegen ') }}</h2>
    <p>{{ \App\Models\RpgCombat::statusLabel($combat->status) }} · Runde {{ $round }} · {{ $seconds }} Spielsekunden</p>
    <p>Entfernung: {{ count($actors) === 2 ? abs($actors[1]['position'] - $actors[2]['position']) / 100 : $combat->distance / 100 }} m</p>
    @if($combat->completed_at)<p class="font-semibold">{{ $combat->winner_side ? 'Gewonnen: '.$combat->participants->firstWhere('side', $combat->winner_side)->character_name : 'Ohne Sieger beendet' }} · {{ $combat->resultLabel() }}</p>@endif
</header>
@if($combat->status === 'challenged')
    @include('rpg.combats.partials.conditions')
    <p>Startentfernung: {{ $combat->distance / 100 }} m. Annahme bis {{ $combat->expires_at->timezone('Europe/Berlin')->format('d.m.Y H:i') }} Uhr.</p>
    @if($side)<form method="POST" action="{{ route('rpg.combats.command', $combat) }}" class="flex flex-wrap gap-3">@csrf<input type="hidden" name="submission_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
        @if($side === 2)<button class="btn btn-primary" name="command" value="accept">Bedingungen annehmen und starten</button><button class="btn btn-outline" name="command" value="decline">Ablehnen</button>
        @else<button class="btn btn-outline" name="command" value="withdraw">Herausforderung zurückziehen</button>@endif
    </form>@endif
@endif
<div class="grid md:grid-cols-2 gap-4">
    @foreach($combat->participants as $participant)
        @php($actor = $actors[$participant->side] ?? null)
        @php($profile = $actor['profile'] ?? $participant->snapshot)
        <article class="border border-base-300 rounded-xl p-4 space-y-2">
            <h3 class="text-xl font-semibold">{{ $participant->character_name }} · Seite {{ $participant->side }}</h3>
            @if($actor)
                <p>Initiative {{ $actor['initiative'] ?? 'offen' }} · Position {{ $actor['position'] / 100 }} m · VM {{ \App\Services\RpgCombat\CombatStats::vm($actor, $rules) }} · PEP {{ $actor['pep'] }}</p>
                <p>Wunden: {{ collect($actor['wounds'])->map(fn($w) => [1 => 'leicht', 2 => 'mittel', 3 => 'schwer', 4 => 'bewusstlos'][$w])->join(', ') ?: 'keine' }}{{ $actor['prone'] ? ' · am Boden' : '' }}</p>
                <p>Geführt: {{ collect($actor['held'])->map(fn($id) => $actor['weapons'][$id]['name'])->join(', ') ?: 'unbewaffnet' }}{{ $actor['shield'] && !$actor['shield_broken'] ? ' + Schild' : '' }}</p>
            @endif
            <details><summary class="cursor-pointer font-semibold">Kampfwerte, Ausrüstung und Eigenschaften</summary>
                <dl class="grid grid-cols-2 gap-1 my-2">@foreach($profile['attributes'] as $name => $value)<dt>{{ strtoupper($name) }}</dt><dd>{{ $value }}</dd>@endforeach
                    @foreach($profile['skills'] as $name => $value)<dt>{{ $name }}</dt><dd>{{ $value }}</dd>@endforeach
                </dl>
                <p>Rüstung: {{ $profile['armor']['name'] ?? 'keine' }} · SF {{ $profile['armor']['protection'] ?? 0 }} · BM {{ $profile['armor']['movementModifier'] ?? 0 }}</p>
                <ul class="list-disc pl-5 my-3">@foreach($actor['weapons'] ?? $profile['weapons'] as $weapon)<li>{{ $weapon['instance'] }} – {{ $weapon['name'] }} ({{ $weapon['hands'] }} Hand/Hände)
                    @foreach($weapon['modes'] as $index => $mode)<p>Modus {{ $index }}: {{ $mode['skill'] }}, {{ strtoupper(implode('/', $mode['attributes'])) }}, S {{ $mode['damage'] }}@if($mode['kind'] === 'ranged'), P {{ $mode['precision'] }}, RI {{ $mode['rangeIncrement'] }} m, MR {{ $mode['maxRange'] }} m, F {{ $mode['fireRate'] }}@endif</p>@endforeach
                    @if($weapon['capacity'] || $weapon['reserve'])<p>Geladen {{ $weapon['loaded'] }} / Reserve {{ $weapon['reserve'] }}</p>@endif
                    @if($weapon['jammed']) Ladehemmung @endif @if($weapon['broken']) Unbrauchbar @endif @if($weapon['position'] !== null) Abgelegt bei {{ $weapon['position'] / 100 }} m @endif
                </li>@endforeach</ul>
                <p>Vorteile: {{ implode(', ', $profile['advantages']) ?: 'keine' }}. Nachteile: {{ implode(', ', $profile['disadvantages']) ?: 'keine' }}.</p>
                <dl class="space-y-2 my-3">@foreach(\App\Services\RpgCombat\CombatTraits::describe($profile) as $name => $explanation)<div><dt class="font-semibold">{{ $name }}</dt><dd>{{ $explanation }} @if(isset($profile['disadvantage_details'][$name]) && is_string($profile['disadvantage_details'][$name])) Festgelegt: {{ $profile['disadvantage_details'][$name] }} @endif</dd></div>@endforeach</dl>
            </details>
        </article>
    @endforeach
</div>
<section class="space-y-4" aria-label="Offene Entscheidungen">
    @foreach($decisions as $decision)
        <article class="border border-primary/40 rounded-xl p-4 space-y-3">
            <h3 class="font-semibold">{{ \App\Models\RpgCombatDecision::typeLabel($decision['type']) }} · {{ $decision['side'] ? 'Seite '.$decision['side'] : 'AG-Leitung' }}</h3>
            <p>{{ $decision['due_at'] ? 'Frist: '.$decision['due_at']->timezone('Europe/Berlin')->format('d.m.Y H:i').' Uhr' : 'Pausiert bis zur Regelauslegung' }}. Danach wird dieser Schritt automatisch entschieden.</p>
            @if($decision['controller'] && $decision['controller'] !== $decision['side'])<p>Durch Beherrschung entscheidet Seite {{ $decision['controller'] }}.</p>@endif
            @if($decision['allowed'])
                @include('rpg.combats.partials.decision')
            @else<p>Du wartest auf die zuständige Person oder die automatische Entscheidung.</p>@endif
        </article>
    @endforeach
</section>
@if($side && $combat->accepted_at && $combat->isOpen())
    <details><summary class="cursor-pointer">Kampf beenden</summary>
        <form method="POST" action="{{ route('rpg.combats.command', $combat) }}" class="flex flex-wrap gap-3 mt-3">@csrf<input type="hidden" name="submission_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            <button class="btn btn-outline" name="command" value="surrender">Aufgeben (Gegner gewinnt)</button>
            <button class="btn btn-outline" name="command" value="abort">{{ $abort_offer && $abort_offer !== $side ? 'Abbruch annehmen' : 'Einvernehmlichen Abbruch anbieten' }}</button>
        </form>
    </details>
@endif
<details><summary class="cursor-pointer font-semibold">Regelauslegungen und Timeout-Standards</summary>
    <p class="my-2">Buchregeln und Simulator-Auslegungen sind getrennt. Die erste Option ist jeweils der Timeout-Standard. Die Leitung darf den eigenen Kampf nicht entscheiden.</p>
    <dl class="space-y-3">@foreach($rulings as $key => $definition)<div><dt class="font-semibold">{{ $definition['id'] }} · {{ $definition['label'] }} (S. {{ $definition['pages'] }})</dt><dd>{{ isset($rules[$key]) ? 'Entschieden: '.$definition['options'][$rules[$key]] : 'Standard: '.reset($definition['options']) }}</dd></div>@endforeach</dl>
</details>
<section aria-label="Kampfprotokoll" class="space-y-3">
    <h2 class="text-xl font-semibold">Kampfprotokoll</h2>
    <p>Neueste Schritte zuerst. Würfel und Modifikatoren werden auf dem Server festgelegt.</p>
    <ol class="space-y-3">@foreach($events as $event)<li class="border-b border-base-300 pb-3">
        <p class="text-sm">#{{ $event->sequence }} · Runde {{ $event->round }} · {{ $event->created_at->timezone('Europe/Berlin')->format('d.m.Y H:i:s') }} · {{ $event->origin === 'timeout' ? 'Automatisch nach Frist' : ($event->origin === 'system' ? 'System' : 'Spielentscheidung') }}{{ $event->side ? ' · Seite '.$event->side : '' }}</p>
        <p class="font-medium">{{ $event->data['message'] ?? $event->kind }}</p>
        @if(isset($event->data['dice']))<p>Würfel: {{ implode(' + ', $event->data['dice']) }} = {{ $event->data['raw'] ?? array_sum($event->data['dice']) }}; Ergebnis {{ $event->data['total'] }}</p>@endif
        @if(isset($event->data['modifiers']))<p>{{ collect($event->data['modifiers'])->map(fn($value, $name) => $name.': '.($value >= 0 ? '+' : '').$value)->join(' · ') }}</p>@endif
        @if(isset($event->data['pages']))<p class="text-sm">Regelwerk S. {{ $event->data['pages'] }}</p>@endif
        <details><summary class="cursor-pointer text-sm">Alle Schrittangaben</summary><pre class="text-xs whitespace-pre-wrap break-words">{{ json_encode($event->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></details>
    </li>@endforeach</ol>
    @if($events->count() === 100)<a class="link" href="{{ route('rpg.combats.show', ['combat' => $combat, 'before' => $events->last()->sequence]) }}">Ältere Schritte</a>@endif
</section>
