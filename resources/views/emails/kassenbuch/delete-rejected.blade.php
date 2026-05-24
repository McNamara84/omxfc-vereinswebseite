<x-mail::message>
# Löschanfrage abgelehnt

Die Löschanfrage von {{ $requester->name }} wurde von {{ $processor->name }} abgelehnt.

- **Beschreibung:** {{ $entry['beschreibung'] }}
- **Datum:** {{ $entry['buchungsdatum'] }}
- **Typ:** {{ $entry['typ_label'] }}
- **Betrag:** {{ $entry['betrag_formatiert'] }}
- **Begründung der Anfrage:** {{ $reasonText }}

@if($rejectionReason)
<x-mail::panel>
**Ablehnungsgrund:** {{ $rejectionReason }}
</x-mail::panel>
@endif

<x-mail::button :url="$kassenbuchUrl">
Zum Kassenbuch
</x-mail::button>
</x-mail::message>