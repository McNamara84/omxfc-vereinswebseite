<x-mail::message>
# Wundan zum Gruße, {{ $user->vorname }}!

Fast fertig! Bitte bestätige deine E-Mail-Adresse. Solange du diese nicht bestätigt hast, wird der Vorstand deinen Antrag nicht zur Prüfung vorgelegt bekommen.

<x-mail::button :url="$verificationUrl" color="success">Mailadresse bestätigen</x-mail::button>

Tuma sa feesa,

Der Vorstand des OMXFC

Tanja, Arndt und Markus
</x-mail::message>