<?php

namespace App\Livewire;

use App\Mail\FantreffenAnmeldungBestaetigung;
use App\Mail\FantreffenNeueAnmeldung;
use App\Models\FantreffenAnmeldung as FantreffenAnmeldungModel;
use App\Models\FantreffenVipAuthor;
use App\Models\User;
use App\Services\FantreffenDeadlineService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class FantreffenAnmeldung extends Component
{
    // Form fields - Validierung nur bei submit, nicht bei jedem Tastendruck
    #[Validate('required|string|max:255', onUpdate: false)]
    public string $vorname = '';

    #[Validate('required|string|max:255', onUpdate: false)]
    public string $nachname = '';

    #[Validate('required|email|max:255', onUpdate: false)]
    public string $email = '';

    #[Validate('nullable|string|max:50')]
    public string $mobile = '';

    public bool $tshirt_bestellt = false;

    #[Validate('required_if:tshirt_bestellt,true|nullable|in:XS,S,M,L,XL,XXL,XXXL')]
    public string $tshirt_groesse = '';

    // Locked: Diese Werte können nicht vom Client manipuliert werden
    #[Locked]
    public bool $tshirtDeadlinePassed = false;

    #[Locked]
    public int $daysUntilDeadline = 0;

    #[Locked]
    public string $tshirtDeadlineFormatted = '';

    public function mount(FantreffenDeadlineService $deadlineService): void
    {
        // T-Shirt Deadline aus zentralem Service laden
        $this->tshirtDeadlinePassed = $deadlineService->isPassed();
        $this->daysUntilDeadline = $deadlineService->getDaysRemaining();
        $this->tshirtDeadlineFormatted = $deadlineService->getFormattedDate();

        // Pre-fill data for logged-in users
        if (Auth::check()) {
            $user = Auth::user();
            $this->vorname = $user->vorname;
            $this->nachname = $user->nachname;
            $this->email = $user->email;
        }
    }

    /**
     * Zahlungsbetrag als Computed Property - automatisch gecached und bei Bedarf neu berechnet.
     */
    #[Computed]
    public function paymentAmount(): int
    {
        $amount = 0;

        if (!Auth::check()) {
            $amount += FantreffenAnmeldungModel::GUEST_FEE;
        }

        if ($this->tshirt_bestellt) {
            $amount += FantreffenAnmeldungModel::TSHIRT_PRICE;
        }

        return $amount;
    }

    /**
     * E-Mail-Warnung als Computed Property - prüft ob die E-Mail bereits einem User gehört.
     */
    #[Computed]
    public function showEmailWarning(): bool
    {
        if (Auth::check()) {
            return false;
        }

        if (empty($this->email)) {
            return false;
        }

        return User::where('email', $this->email)->exists();
    }

    public function updatedTshirtBestellt(): void
    {
        // Reset T-Shirt size if unchecked
        if (!$this->tshirt_bestellt) {
            $this->tshirt_groesse = '';
        }
        // Computed Property wird automatisch neu berechnet
        unset($this->paymentAmount);
    }

    public function updatedEmail(): void
    {
        // Computed Property für Email-Warnung neu berechnen
        unset($this->showEmailWarning);
    }

    public function submit()
    {
        // DEBUG: Log form submission attempt
        \Log::info('FantreffenAnmeldung: Form submit started', [
            'tshirt_bestellt' => $this->tshirt_bestellt,
            'tshirt_groesse' => $this->tshirt_groesse,
            'mobile' => $this->mobile,
            'isLoggedIn' => Auth::check(),
        ]);

        // Dynamische Regeln für eingeloggte vs. nicht-eingeloggte User
        $rules = [
            'mobile' => 'nullable|string|max:50',
            'tshirt_bestellt' => 'boolean',
            'tshirt_groesse' => 'required_if:tshirt_bestellt,true|nullable|in:XS,S,M,L,XL,XXL,XXXL',
        ];

        if (!Auth::check()) {
            $rules['vorname'] = 'required|string|max:255';
            $rules['nachname'] = 'required|string|max:255';
            $rules['email'] = 'required|email|max:255';
        }

        $messages = [
            'vorname.required' => 'Bitte gib deinen Vornamen an.',
            'nachname.required' => 'Bitte gib deinen Nachnamen an.',
            'email.required' => 'Bitte gib deine E-Mail-Adresse an.',
            'email.email' => 'Bitte gib eine gültige E-Mail-Adresse an.',
            'mobile.string' => 'Bitte gib eine gültige Telefonnummer an.',
            'tshirt_groesse.required_if' => 'Bitte wähle eine T-Shirt-Größe aus.',
            'tshirt_groesse.in' => 'Bitte wähle eine gültige T-Shirt-Größe aus.',
        ];

        try {
            // Validate form
            $this->validate($rules, $messages);
            \Log::info('FantreffenAnmeldung: Validation passed');
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('FantreffenAnmeldung: Validation failed', [
                'errors' => $e->errors(),
            ]);
            throw $e;
        }

        // Check T-Shirt deadline
        if ($this->tshirt_bestellt && $this->tshirtDeadlinePassed) {
            \Log::warning('FantreffenAnmeldung: T-Shirt deadline passed');
            session()->flash('error', 'Die Deadline für T-Shirt-Bestellungen ist leider abgelaufen.');

            return;
        }

        // Get payment amount from computed property
        $paymentAmount = $this->paymentAmount;
        \Log::info('FantreffenAnmeldung: Payment calculated', ['amount' => $paymentAmount]);

        // Create registration
        try {
            $anmeldung = FantreffenAnmeldungModel::create([
                'user_id' => Auth::id(),
                'vorname' => Auth::check() ? Auth::user()->vorname : $this->vorname,
                'nachname' => Auth::check() ? Auth::user()->nachname : $this->nachname,
                'email' => Auth::check() ? Auth::user()->email : $this->email,
                'mobile' => $this->mobile,
                'tshirt_bestellt' => $this->tshirt_bestellt,
                'tshirt_groesse' => $this->tshirt_bestellt ? $this->tshirt_groesse : null,
                'payment_status' => $paymentAmount == 0 ? 'free' : 'pending',
                'payment_amount' => $paymentAmount,
                'ist_mitglied' => Auth::check(),
            ]);
            \Log::info('FantreffenAnmeldung: Registration created', ['id' => $anmeldung->id]);
        } catch (\Exception $e) {
            \Log::error('FantreffenAnmeldung: Failed to create registration', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            session()->flash('error', 'Fehler beim Speichern der Anmeldung. Bitte versuche es erneut oder kontaktiere uns.');

            return;
        }

        // Send confirmation email to participant
        try {
            Mail::to($anmeldung->registrant_email)
                ->send(new FantreffenAnmeldungBestaetigung($anmeldung));
            \Log::info('FantreffenAnmeldung: Confirmation email sent');
        } catch (\Exception $e) {
            \Log::error('FantreffenAnmeldung: Failed to send confirmation email', ['error' => $e->getMessage()]);
        }

        // Send notification to board
        try {
            Mail::to('vorstand@maddrax-fanclub.de')
                ->send(new FantreffenNeueAnmeldung($anmeldung));
            \Log::info('FantreffenAnmeldung: Notification email sent to board');
        } catch (\Exception $e) {
            \Log::error('FantreffenAnmeldung: Failed to send notification email', ['error' => $e->getMessage()]);
        }

        // Setze Session-Token für Zugriff auf Bestätigungsseite (für nicht eingeloggte Nutzer)
        if (! Auth::check()) {
            session()->put('fantreffen_anmeldung_'.$anmeldung->id, true);
        }

        \Log::info('FantreffenAnmeldung: Redirecting to confirmation page');

        // Weiterleitung zur Zahlungsbestätigungsseite
        return redirect()->route('fantreffen.2026.bestaetigung', ['id' => $anmeldung->id]);
    }

    private function resetForm(): void
    {
        if (!Auth::check()) {
            $this->vorname = '';
            $this->nachname = '';
            $this->email = '';
        }
        $this->mobile = '';
        $this->tshirt_bestellt = false;
        $this->tshirt_groesse = '';
        // Computed Properties werden automatisch zurückgesetzt
        unset($this->showEmailWarning, $this->paymentAmount);
    }

    public function render()
    {
        $vipAuthors = FantreffenVipAuthor::active()->ordered()->get();

        $description = 'Melde dich jetzt an zum Maddrax-Fantreffen am 9. Mai 2026 in Köln. ';
        if ($vipAuthors->isNotEmpty()) {
            $description .= 'Mit VIP-Autoren: '.$vipAuthors->pluck('display_name')->implode(', ').'. ';
        }
        $description .= 'Signierstunde und Verleihung der Goldenen Taratze.';

        return view('livewire.fantreffen-anmeldung', [
            'isLoggedIn' => Auth::check(),
            'user' => Auth::user(),
            'vipAuthors' => $vipAuthors,
        ])->layout('layouts.app', [
            'title' => 'Maddrax-Fantreffen 2026 – Offizieller MADDRAX Fanclub e. V.',
            'description' => $description,
        ]);
    }
}
