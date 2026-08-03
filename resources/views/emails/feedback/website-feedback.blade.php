<x-mail::message>
# Neues Website-Feedback

Ein Mitglied hat Feedback zur Vereinswebsite eingereicht.

- **Kategorie:** {{ $feedback->category->label() }}
- **Seite:** {{ $feedback->pageTitle }}
- **Zeitpunkt:** {{ $submittedAt }}
@if ($feedback->isAnonymous())
- **Absender:** Anonym eingereicht
@else
- **Absender:** {{ $feedback->reporterName }}
- **Antwortadresse:** {{ $feedback->reporterEmail }}
@endif

<x-mail::button :url="$feedback->pageUrl">
Betroffene Seite öffnen
</x-mail::button>

## Nachricht

<div style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $feedback->message }}</div>

<x-mail::panel>
Dieses Feedback wurde über die Vereinswebsite eingereicht. Bei anonymem Versand speichert die Website keine Zuordnung des Inhalts zum Mitglied.
</x-mail::panel>
</x-mail::message>
