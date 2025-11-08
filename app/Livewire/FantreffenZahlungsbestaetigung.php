<?php

namespace App\Livewire;

use App\Models\FantreffenAnmeldung;
use Livewire\Component;

class FantreffenZahlungsbestaetigung extends Component
{
    public $anmeldung;
    public $paypalMeUrl;

    public function mount($id)
    {
        $this->anmeldung = FantreffenAnmeldung::with('user')->findOrFail($id);

        // Prüfe ob Nutzer berechtigt ist, diese Anmeldung zu sehen
        if (auth()->check()) {
            // Eingeloggte Nutzer dürfen nur ihre eigene Anmeldung sehen
            if ($this->anmeldung->user_id !== auth()->id()) {
                abort(403, 'Zugriff verweigert.');
            }
        } else {
            // Nicht eingeloggte Nutzer dürfen nur direkt nach der Anmeldung zugreifen
            // (wird über Session-Token validiert)
            if (!session()->has('fantreffen_anmeldung_' . $id)) {
                abort(403, 'Zugriff verweigert. Bitte melden Sie sich an.');
            }
        }

        // Generiere PayPal.me URL mit vorausgefülltem Betrag (nur wenn Zahlung erforderlich)
        if ($this->anmeldung->payment_status === 'pending' && $this->anmeldung->payment_amount > 0) {
            // PayPal.me Format: https://paypal.me/username/betrag?locale.x=de_DE
            // Betrag muss als EUR angegeben werden
            $amount = number_format((float) $this->anmeldung->payment_amount, 2, '.', '');
            $paypalUsername = config('services.paypal.me_username', 'OfficialMaddraxFanclub');
            
            $this->paypalMeUrl = "https://paypal.me/{$paypalUsername}/{$amount}EUR?locale.x=de_DE";
        }
    }

    public function render()
    {
        return view('livewire.fantreffen-zahlungsbestaetigung');
    }

    public function layout()
    {
        return 'layouts.app';
    }
}
