<x-mail::message>
# Maddraxikon-Konto verknüpft

Hallo,

dein Maddraxikon-Konto **{{ $wikiUsername }}** wurde am
{{ $verifiedAt
    ->setTimezone(config('maddraxikon.timezone', 'Europe/Berlin'))
    ->locale('de')
    ->isoFormat('D. MMMM YYYY [um] HH:mm [Uhr]') }}
erfolgreich mit deinem Vereinskonto verknüpft.

Qualifizierte Beiträge, die du ab diesem Zeitpunkt im Maddraxikon erstellst,
können nach der vorgesehenen Warte- und Prüfzeit Baxx erhalten. Deine
Maddraxikon-E-Mailadresse wurde dafür weder angefordert noch gespeichert.

<x-mail::button :url="$profileUrl">
Verknüpfung verwalten
</x-mail::button>

Du kannst die Verbindung dort jederzeit wieder trennen. Bereits
gutgeschriebene Baxx bleiben dabei erhalten.

Viele Grüße  
Dein OMXFC-Team
</x-mail::message>
