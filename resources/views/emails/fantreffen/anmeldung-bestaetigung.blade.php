<x-mail::message>
# Wundan zum Gruße, {{ $fullName }}!

Deine Anmeldung zum **Maddrax-Fantreffen 2026** ist bei uns eingegangen!

## Event-Details

**Datum:** Freitag, 9. Mai 2026  
**Beginn:** 19:00 Uhr  
**Ort:** L´Osteria Köln Mülheim  
[Zur Google Maps](https://maps.app.goo.gl/dzLHUqVHqJrkWDkr5)

## Programm

- **19:00 Uhr** – Signierstunde mit Maddrax-Autoren
- **20:00 Uhr** – Verleihung der Goldenen Taratze

## Deine Anmeldedaten

@if($anmeldung->ist_mitglied)
**Status:** Vereinsmitglied  
**Kosten:** Kostenlose Teilnahme
@else
**Status:** Gast  
**Kosten:** {{ number_format(\App\Models\FantreffenAnmeldung::GUEST_FEE, 2, ',', '.') }} € Spende erbeten
@endif

@if($anmeldung->tshirt_bestellt)
**T-Shirt:** Ja, Größe {{ $anmeldung->tshirt_groesse }}  
**T-Shirt-Spende:** {{ $anmeldung->getFormattedTshirtPrice() }}
@else
**T-Shirt:** Nicht bestellt
@endif

@if($anmeldung->mobile)
**Mobile Rufnummer:** {{ $anmeldung->mobile }}  
(für kurzfristige Programmänderungen via WhatsApp)
@endif

@if($paymentRequired)
---

**Zahlungsstatus:** {{ $anmeldung->payment_status === 'paid' ? 'Bezahlt' : 'Ausstehend' }}  
@if($anmeldung->payment_status === 'paid')
Vielen Dank für deine Spende von **{{ number_format((float) $paymentAmount, 2, ',', '.') }} €**!
@else
**Zu zahlender Betrag:** {{ number_format((float) $paymentAmount, 2, ',', '.') }} €

<x-mail::button :url="$zahlungsUrl">
Jetzt mit PayPal zahlen
</x-mail::button>

Bitte wähle bei PayPal die Option **"Freunde & Familie"**, um Gebühren zu vermeiden.  
Empfänger: vorstand@maddrax-fanclub.de
@endif
@endif

---

Wir freuen uns auf dich beim Maddrax-Fantreffen 2026!

**Hinweis:** Am selben Wochenende findet auch die ColoniaCon statt, wo der Fanclub ebenfalls mit Programmpunkten vertreten sein wird.

Tuma sa feesa,

Der Vorstand des OMXFC  
Tanja, Arndt und Markus

---

<x-mail::subcopy>
Bei Fragen zur Anmeldung wende dich bitte an: [vorstand@maddrax-fanclub.de](mailto:vorstand@maddrax-fanclub.de)
</x-mail::subcopy>
</x-mail::message>
