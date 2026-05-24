<x-mail::message>
# Neue Nachricht fuer {{ $team->name }}

Es wurde eine neue Kontaktanfrage ueber die oeffentliche Arbeitsgruppen-Seite gesendet.

- **Arbeitsgruppe:** {{ $team->name }}
- **Absender:** {{ $absenderName }}
- **Antwort an:** {{ $absenderEmail }}

## Nachricht

{{ $nachricht }}
</x-mail::message>