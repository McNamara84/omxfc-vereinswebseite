<x-mail::message>
{{ match ($delivery->kind) { 'invitation' => 'Du wurdest zu einem Übungskampf herausgefordert.', 'started' => 'Dein Übungskampf wurde angenommen.', 'handover' => 'Als neue AG-Leitung übernimmst du den NSC und die offenen Leitungsentscheidungen dieses Übungskampfs.', 'reminder' => 'Erinnerung: Deine persönliche Leitungsentscheidung ist seit 24 Stunden offen.', default => 'Im Übungskampf ist eine Entscheidung von dir offen.' } }}

@if($decision?->due_at)
Deine Entscheidung ist bis {{ $decision->due_at->timezone('Europe/Berlin')->format('d.m.Y H:i') }} Uhr möglich. Danach gilt die veröffentlichte Standardentscheidung. Jeder neue Schritt erhält wieder 24 Stunden.
@elseif($decision?->timeout_policy === 'manual_leader')
Die AG-Leitung entscheidet für den NSC beziehungsweise die offene Regelfrage. Nach 24 Stunden erfolgt eine einmalige Erinnerung. Es gibt keine automatische Ersatzentscheidung; deine Eingabe bleibt möglich.
@elseif($delivery->kind === 'invitation')
Die Einladung verfällt am {{ $combat->expires_at->timezone('Europe/Berlin')->format('d.m.Y H:i') }} Uhr. Der Kampf beginnt erst nach deiner Annahme.
@endif

{{ $combat->participants->pluck('character_name')->join(' gegen ') }}

<x-mail::button :url="route('rpg.combats.show', $combat)">Übungskampf öffnen</x-mail::button>
</x-mail::message>
