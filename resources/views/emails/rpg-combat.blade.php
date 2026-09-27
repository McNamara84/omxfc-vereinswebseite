<x-mail::message>
{{ $delivery->kind === 'invitation' ? 'Du wurdest zu einem Übungskampf herausgefordert.' : ($delivery->kind === 'started' ? 'Dein Übungskampf wurde angenommen.' : 'Im Übungskampf ist eine Entscheidung von dir offen.') }}

@if($decision?->due_at)
Deine Entscheidung ist bis {{ $decision->due_at->timezone('Europe/Berlin')->format('d.m.Y H:i') }} Uhr möglich. Danach gilt die veröffentlichte Standardentscheidung. Jeder neue Schritt erhält wieder 24 Stunden.
@elseif($delivery->kind === 'invitation')
Die Einladung verfällt am {{ $combat->expires_at->timezone('Europe/Berlin')->format('d.m.Y H:i') }} Uhr. Der Kampf beginnt erst nach deiner Annahme.
@endif

<x-mail::button :url="route('rpg.combats.show', $combat)">Übungskampf öffnen</x-mail::button>
</x-mail::message>
