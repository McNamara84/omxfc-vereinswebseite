<x-mail::message>
# Neue Anmeldung zum Maddrax-Fantreffen 2026

Es liegt eine neue Anmeldung für das Maddrax-Fantreffen am 9. Mai 2026 vor.

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
**T-Shirt-Spende:** {{ $anmeldung->ist_mitglied ? '25,00 €' : '30,00 €' }}
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

<x-mail::button :url="route('admin.fantreffen.2026')">
Zur Admin-Übersicht
</x-mail::button>

---

**Anmeldung vom:** {{ $anmeldung->created_at->format('d.m.Y H:i') }} Uhr

Diese E-Mail wurde automatisch generiert.
</x-mail::message>
