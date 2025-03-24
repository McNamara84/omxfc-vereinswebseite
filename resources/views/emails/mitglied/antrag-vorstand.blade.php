<x-mail::message>
# Neuer Mitgliedsantrag eingegangen

Eine Person hat einen neuen Mitgliedsantrag gestellt. Bitte prüfe den Antrag mit den folgenden Daten:

- **Name:** {{ $user->name }}
- **Adresse:** {{ $user->strasse }} {{ $user->hausnummer }}, {{ $user->plz }} {{ $user->stadt }}, {{ $user->land }}
- **E-Mail:** {{ $user->email }}
- **Telefon:** {{ $user->telefon ?? 'nicht angegeben' }}
- **Gewünschter Mitgliedsbeitrag:** {{ $user->mitgliedsbeitrag }}€
- **Wie gefunden:** {{ $user->verein_gefunden ?? 'nicht angegeben' }}

<x-mail::panel>
    **Hinweis für den Kassenwart:** Bitte Zahlungsinformationen an den Antragsteller senden. Bei Zahlungseingang den Antrag in der Mitgliederverwaltung bestätigen.
</x-mail::panel>
</x-mail::message>
