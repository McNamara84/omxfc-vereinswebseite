<div class="rounded-xl bg-base-200 p-4 space-y-2 text-sm">
    <p>Offene, ebene Arena bei diffusem Tageslicht. Keine Deckung, Begleiter, Reittiere oder Fahrzeuge. Normale Versorgung und funktionsfähige Ausrüstung werden vorausgesetzt.</p>
    <p>Es kämpfen Kopien der gespeicherten Charaktere. Wunden, Munition und PEP verändern die Originale nicht. Es gibt keine EP, Baxx oder Rangliste.</p>
    <p>Jede Entscheidung erhält 24 Stunden. Danach wird nur dieser Schritt automatisch erledigt; der nächste erhält wieder 24 Stunden. Ein Duell kann dadurch mehrere Wochen dauern. Die Einladung verfällt nach sieben Tagen.</p>
    <p>Bewusstlosigkeit, Aufgabe oder einvernehmlicher Abbruch beenden den Kampf. Als Simulatorgrenze gelten {{ $combat->round_limit ?? config('rpg-combat.round_limit') }} Runden, anschließend unentschieden. Eine Runde entspricht drei Spielsekunden; reale Wartezeit zählt nicht als Spielzeit.</p>
    <p>Offene Regelfragen entscheidet die aktuelle AG-Leitung. Ist sie selbst beteiligt oder reagiert sie nicht, gilt nach 24 Stunden die im Kampf einsehbare Standardauslegung.</p>
</div>
