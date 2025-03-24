<x-mail::message>
# Wundan zum Gruße, {{ $user->vorname }}!

Dein Mitgliedschaftsantrag wird nun geprüft. Dies geschieht manuell. Lass uns etwas Zeit um deinen Antrag zu prüfen. Wir informieren dich, sobald dein Antrag bearbeitet wurde und senden dir dann auch direkt die Zahlungsdaten für den Mitgliedsbeitrag zu.
    
Bis es so weit ist bestätige schon mal deinen Account, indem du auf den folgenden Button klickst.

<x-mail::button :url="$verificationUrl" color="success">Mailadresse bestätigen</x-mail::button>

Tuma sa feesa,

Der Vorstand des OMXFC

Tanja, Arndt und Markus
</x-mail::message>