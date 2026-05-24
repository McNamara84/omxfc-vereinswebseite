<x-mail::message>
# Kassenbucheintrag wurde gelöscht

Die Löschanfrage von {{ $requester->name }} wurde von {{ $processor->name }} freigegeben und ausgeführt.

- **Beschreibung:** {{ $entry['beschreibung'] }}
- **Datum:** {{ $entry['buchungsdatum'] }}
- **Typ:** {{ $entry['typ_label'] }}
- **Betrag:** {{ $entry['betrag_formatiert'] }}
- **Begründung der Anfrage:** {{ $reasonText }}

<x-mail::button :url="$kassenbuchUrl">
Zum Kassenbuch
</x-mail::button>
</x-mail::message>