<x-mail::message>
# Kontaktdaten aktualisiert

{{ $user->name }} hat freigegebene Kontaktdaten im Profil aktualisiert.

- **Profil:** [{{ $user->name }}]({{ $profileUrl }})
- **Geänderte Kontaktwege:** {{ implode(', ', $changedContactLabels) }}
- **Zeitpunkt:** {{ $contactChangedAt->timezone(config('app.timezone'))->format('d.m.Y H:i') }}

<x-mail::panel>
Newsletter-Versand, CSV-Export und die Funktion zum Kopieren aller E-Mail-Adressen verwenden weiterhin die hinterlegte Konto-E-Mail-Adresse.
</x-mail::panel>
</x-mail::message>
