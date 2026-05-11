<x-mail::message>
# Neue Anmeldung zu {{ $veranstaltung?->titel ?? 'einer Veranstaltung' }}

Es liegt eine neue Anmeldung vor.

@if($veranstaltung)
## Veranstaltung

**Titel:** {{ $veranstaltung->titel }}  
@if($veranstaltung->datum_von)
**Datum:** {{ $veranstaltung->datum_von->locale('de')->isoFormat('D. MMMM YYYY, HH:mm') }} Uhr  
@endif
@if($veranstaltung->ort_name)
**Ort:** {{ $veranstaltung->ort_name }}
@endif
@endif

## Teilnehmerdaten

**Name:** {{ $fullName }}  
**E-Mail:** {{ $email }}  
@if($anmeldung->mobile)
**Mobile:** {{ $anmeldung->mobile }}  
@endif

**Typ:** {{ $anmeldung->ist_mitglied ? 'Vereinsmitglied' : 'Gast' }}

@if($anmeldung->user_id)
**User-ID:** {{ $anmeldung->user_id }}
@endif

## Bestelldetails

@if($anmeldung->tshirt_bestellt)
**T-Shirt:** ✅ Ja  
**Größe:** {{ $anmeldung->tshirt_groesse }}  
**T-Shirt-Spende:** {{ $anmeldung->getFormattedTshirtPrice() }}
@else
**T-Shirt:** ❌ Nicht bestellt
@endif

## Zahlungsinformationen

**Zahlungsstatus:** {{ $anmeldung->payment_status === 'paid' ? '✅ Bezahlt' : '⏳ Ausstehend' }}  
@if($anmeldung->payment_status === 'free')
**Betrag:** Kostenlose Teilnahme (Mitglied)
@else
**Betrag:** {{ number_format($anmeldung->payment_amount, 2, ',', '.') }} €
@endif

@if($anmeldung->paypal_transaction_id)
**PayPal Transaktions-ID:** {{ $anmeldung->paypal_transaction_id }}
@endif

---

<x-mail::button :url="$veranstaltung ? route('admin.veranstaltungen.anmeldungen', $veranstaltung) : route('admin.veranstaltungen.index')">
Zur Veranstaltungsverwaltung
</x-mail::button>

---

**Anmeldung vom:** {{ $anmeldung->created_at->format('d.m.Y H:i') }} Uhr

Diese E-Mail wurde automatisch generiert.
</x-mail::message>
