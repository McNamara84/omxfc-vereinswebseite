@php($npcCombat = ($npcCombat ?? false) || (isset($combat) && $combat->kind === 'npc_vs_player'))
<div class="rounded-xl bg-base-200 p-4 space-y-2 text-sm">
    <p>Offene, ebene Arena bei diffusem Tageslicht. Keine Deckung, Begleiter, Reittiere oder Fahrzeuge. Normale Versorgung und funktionsfähige Ausrüstung werden vorausgesetzt.</p>
    <p>Es kämpfen Kopien der gespeicherten Charaktere. Wunden, Munition und PEP verändern die Originale nicht. Es gibt keine EP, Baxx oder Rangliste.</p>
    <p>@if($npcCombat)Spielereingaben haben eine Frist von 24 Stunden und werden danach automatisch entschieden. Die Leitung entscheidet NSC-Eingaben und Regelfragen persönlich; nach 24 Stunden erhält sie eine einmalige Erinnerung. @else Jede Entscheidung erhält 24 Stunden. Danach wird nur dieser Schritt automatisch erledigt; der nächste erhält wieder 24 Stunden. @endif Ein Duell kann dadurch mehrere Wochen dauern. Die Einladung verfällt nach sieben Tagen.</p>
    <p>Bewusstlosigkeit, Aufgabe oder einvernehmlicher Abbruch beenden den Kampf. Als Simulatorgrenze gelten {{ $combat->round_limit ?? config('rpg-combat.round_limit') }} Runden, anschließend unentschieden. Eine Runde entspricht drei Spielsekunden; reale Wartezeit zählt nicht als Spielzeit.</p>
    <p>Offene Regelfragen entscheidet die aktuelle AG-Leitung. @if($npcCombat)Sie darf in diesem Kampf auch den NSC steuern. Ohne aktive Leitung pausiert der Kampf bis zur Übernahme.@else Ist sie selbst beteiligt oder reagiert sie nicht, gilt nach 24 Stunden die im Kampf einsehbare Standardauslegung.@endif</p>
</div>
