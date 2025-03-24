<x-mail::message>
    # Wundan zum Gruße, lieber {{ $user->vorname }}!

    Dein Mitgliedschaftsantrag wird nun geprüft. Dies kann bis zu drei Tage dauern. Wir informieren dich, sobald dein Antrag bearbeitet wurde und senden dir dann auch direkt die Zahlungsdaten für den Mitgliedsbeitrag zu.
    
    Bis es so weit ist bestätige schon mal deinen Account, indem du auf den folgenden Button klickst:

    <x-mail::button :url="$verificationUrl">
        Mailadresse bestätigen
    </x-mail::button>

    Tuma sa feesa,<br>
    Der Vorstand des OMXFC<br>
    Tanja, Arndt und Markus
</x-mail::message>