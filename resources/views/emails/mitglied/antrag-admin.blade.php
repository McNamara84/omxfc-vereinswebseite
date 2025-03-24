<x-mail::message>
# Neuer Mitgliedsantrag

Ein neuer Mitgliedsantrag wurde eingereicht von:

- **Name:** {{ $user->name }}
- **Adresse:** {{ $user->strasse }} {{ $user->hausnummer }}, {{ $user->plz }} {{ $user->stadt }}, {{ $user->land }}
- **E-Mail:** {{ $user->email }}
- **Telefon:** {{ $user->telefon ?? '-' }}
- **Mitgliedsbeitrag:** {{ $user->mitgliedsbeitrag }}€
- **Wie gefunden:** {{ $user->verein_gefunden ?? '-' }}

</x-mail::message>
