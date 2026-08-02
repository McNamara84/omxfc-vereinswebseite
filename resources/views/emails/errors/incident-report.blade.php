<x-mail::message>
# Unerwarteter Anwendungsfehler

In der Produktionsumgebung ist ein unerwarteter Fehler aufgetreten.

<x-mail::panel>
- **Vorfall-ID:** {{ $incident->id }}
- **Korrelations-ID:** {{ $incident->correlationId }}
- **Zeitpunkt:** {{ $occurredAt }}
</x-mail::panel>

## Fehler

- **Klasse:** {{ $incident->exceptionClass }}
- **Meldung:** {{ $exceptionMessage !== '' ? $exceptionMessage : 'Keine Fehlermeldung verfügbar' }}
- **Datei:** {{ $incident->exceptionFile }}:{{ $incident->exceptionLine }}
- **Fingerprint:** {{ $incident->fingerprint }}

## Ausführung

- **Art:** {{ $executionLabel }}
- **Ausführung:** {{ $incident->executionName ?? 'Nicht verfügbar' }}
- **URL:** {{ $incident->url ?? 'Nicht verfügbar' }}
- **Route:** {{ $incident->route ?? 'Nicht verfügbar' }}
- **HTTP-Methode:** {{ $incident->method ?? 'Nicht verfügbar' }}
- **Umgebung:** {{ $incident->environment }}
- **Anwendungsversion:** {{ $incident->applicationVersion }}

## Nutzer und Browser

- **Benutzer-ID:** {{ $incident->userId ?? 'Nicht verfügbar' }}
- **Name:** {{ $incident->userName ?? 'Nicht verfügbar' }}
- **E-Mail:** {{ $incident->userEmail ?? 'Nicht verfügbar' }}
- **Aktives Team:** {{ $incident->activeTeamName }}
- **Rolle im aktiven Team:** {{ $incident->activeTeamRole }}
- **Rolle im Mitglieder-Team:** {{ $incident->membersTeamRole }}
- **Browser:** {{ $incident->browser }} {{ $incident->browserVersion }}

@if ($incident->suppressedOccurrences > 0)
<x-mail::panel>
Seit der vorherigen Benachrichtigung wurden **{{ $incident->suppressedOccurrences }} identische Vorkommnisse** unterdrückt.
</x-mail::panel>
@endif

Der angehängte TXT-Auszug enthält den bereinigten Laravel-Exception-Block und weitere zugeordnete Logeinträge. Request-Inhalte, Query-Werte, Cookies und Sessiondaten werden für diesen Bericht nicht aktiv erfasst.

</x-mail::message>
