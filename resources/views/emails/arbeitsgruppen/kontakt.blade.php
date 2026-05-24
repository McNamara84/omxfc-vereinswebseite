<x-mail::message>
# Neue Nachricht für {{ $team->name }}

Es wurde eine neue Kontaktanfrage über die öffentliche Arbeitsgruppen-Seite gesendet.

- **Arbeitsgruppe:** {{ $team->name }}
- **Absender:** {{ $absenderName }}
- **Antwort an:** {{ $absenderEmail }}

## Nachricht

{{ $nachricht }}
</x-mail::message>