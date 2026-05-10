<x-mail::message>
# Wundan zum Gruße, {{ $fullName }}!

Deine Anmeldung zu **{{ $veranstaltung?->titel ?? 'unserer Veranstaltung' }}** ist bei uns eingegangen!

## Event-Details

@if($veranstaltung?->datum_von)
**Datum:** {{ $veranstaltung->datum_von->locale('de')->isoFormat('D. MMMM YYYY, HH:mm') }} Uhr  
@endif
@if($veranstaltung?->ort_name)
**Ort:** {{ $veranstaltung->ort_name }}  
@endif
@if($veranstaltung?->ort_adresse)
**Adresse:** {{ $veranstaltung->ort_adresse }}  
@endif
@if($veranstaltung?->maps_url)
[Zur Google Maps]({{ $veranstaltung->maps_url }})
@endif

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
Empfänger: {{ $veranstaltung?->kontaktEmail() ?? 'vorstand@maddrax-fanclub.de' }}
@endif
@endif

---

Wir freuen uns auf dich bei {{ $veranstaltung?->titel ?? 'der Veranstaltung' }}!

Tuma sa feesa,

Der Vorstand des OMXFC  
Tanja, Arndt und Markus

---

<x-mail::subcopy>
Bei Fragen zur Anmeldung wende dich bitte an: [{{ $veranstaltung?->kontaktEmail() ?? 'vorstand@maddrax-fanclub.de' }}](mailto:{{ $veranstaltung?->kontaktEmail() ?? 'vorstand@maddrax-fanclub.de' }})
</x-mail::subcopy>
</x-mail::message>
