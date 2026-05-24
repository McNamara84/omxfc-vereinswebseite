<x-mail::message>
# Neue Löschanfrage im Kassenbuch

{{ $requester->name }} hat die Löschung eines Kassenbucheintrags angefragt.

- **Beschreibung:** {{ $entry['beschreibung'] }}
- **Datum:** {{ $entry['buchungsdatum'] }}
- **Typ:** {{ $entry['typ_label'] }}
- **Betrag:** {{ $entry['betrag_formatiert'] }}
- **Begründung:** {{ $reasonText }}

<x-mail::button :url="$kassenbuchUrl">
Zur Freigabe im Kassenbuch
</x-mail::button>
</x-mail::message>