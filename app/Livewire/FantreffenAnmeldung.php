<?php

namespace App\Livewire;

use App\Mail\FantreffenAnmeldungBestaetigung;
use App\Mail\FantreffenNeueAnmeldung;
use App\Models\FantreffenAnmeldung as FantreffenAnmeldungModel;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class FantreffenAnmeldung extends Component
{
    // Form fields
    public $vorname = '';
    public $nachname = '';
    public $email = '';
    public $mobile = '';
    public $tshirt_bestellt = false;
    public $tshirt_groesse = '';

    // UI state
    public $showEmailWarning = false;
    public $paymentAmount = 0;

    // T-Shirt deadline
    public $tshirtDeadlinePassed = false;
    public $daysUntilDeadline = 0;

    protected function rules()
    {
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

        return $rules;
    }

    protected $messages = [
        'vorname.required' => 'Bitte gib deinen Vornamen an.',
        'nachname.required' => 'Bitte gib deinen Nachnamen an.',
        'email.required' => 'Bitte gib deine E-Mail-Adresse an.',
        'email.email' => 'Bitte gib eine gültige E-Mail-Adresse an.',
        'mobile.string' => 'Bitte gib eine gültige Telefonnummer an.',
        'tshirt_groesse.required_if' => 'Bitte wähle eine T-Shirt-Größe aus.',
        'tshirt_groesse.in' => 'Bitte wähle eine gültige T-Shirt-Größe aus.',
    ];

    public function mount()
    {
        // Check T-Shirt deadline (28.02.2026)
        $deadline = Carbon::create(2026, 2, 28, 23, 59, 59);
        $this->tshirtDeadlinePassed = Carbon::now()->isAfter($deadline);
        $this->daysUntilDeadline = max(0, Carbon::now()->diffInDays($deadline, false));

        // Pre-fill data for logged-in users
        if (Auth::check()) {
            $user = Auth::user();
            $this->vorname = $user->vorname;
            $this->nachname = $user->nachname;
            $this->email = $user->email;
        }
    }

    public function updated($propertyName)
    {
        // Real-time validation
        $this->validateOnly($propertyName);

        // Check if email belongs to a user
        if ($propertyName === 'email' && !Auth::check()) {
            $this->checkEmailWarning();
        }

        // Calculate payment amount when T-Shirt is toggled
        if (in_array($propertyName, ['tshirt_bestellt'])) {
            $this->calculatePayment();
        }
    }

    public function updatedTshirtBestellt()
    {
        // Reset T-Shirt size if unchecked
        if (!$this->tshirt_bestellt) {
            $this->tshirt_groesse = '';
        }
        $this->calculatePayment();
    }

    private function checkEmailWarning()
    {
        $user = User::where('email', $this->email)->first();
        $this->showEmailWarning = $user !== null;
    }

    private function calculatePayment()
    {
        $isLoggedIn = Auth::check();
        $amount = 0;

        if (!$isLoggedIn) {
            $amount += FantreffenAnmeldungModel::GUEST_FEE;
        }

        if ($this->tshirt_bestellt) {
            $amount += FantreffenAnmeldungModel::TSHIRT_PRICE;
        }

        $this->paymentAmount = $amount;
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

        try {
            // Validate form
            $this->validate();
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

        // Calculate payment
        $this->calculatePayment();
        \Log::info('FantreffenAnmeldung: Payment calculated', ['amount' => $this->paymentAmount]);

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
                'payment_status' => $this->paymentAmount == 0 ? 'free' : 'pending',
                'payment_amount' => $this->paymentAmount,
                'ist_mitglied' => Auth::check(),
            ]);
            \Log::info('FantreffenAnmeldung: Registration created', ['id' => $anmeldung->id]);
        } catch (\Exception $e) {
            \Log::error('FantreffenAnmeldung: Failed to create registration', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            session()->flash('error', 'Fehler beim Speichern der Anmeldung: ' . $e->getMessage());
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
        if (!Auth::check()) {
            session()->put('fantreffen_anmeldung_' . $anmeldung->id, true);
        }

        \Log::info('FantreffenAnmeldung: Redirecting to confirmation page');
        // Weiterleitung zur Zahlungsbestätigungsseite
        return redirect()->route('fantreffen.2026.bestaetigung', ['id' => $anmeldung->id]);
    }

    private function resetForm()
    {
        if (!Auth::check()) {
            $this->vorname = '';
            $this->nachname = '';
            $this->email = '';
        }
        $this->mobile = '';
        $this->tshirt_bestellt = false;
        $this->tshirt_groesse = '';
        $this->showEmailWarning = false;
        $this->paymentAmount = 0;
    }

    public function render()
    {
        return view('livewire.fantreffen-anmeldung', [
            'isLoggedIn' => Auth::check(),
            'user' => Auth::user(),
        ])->layout('layouts.app', [
            'title' => 'Maddrax-Fantreffen 2026 – Offizieller MADDRAX Fanclub e. V.',
            'description' => 'Melde dich jetzt an zum Maddrax-Fantreffen am 9. Mai 2026 in Köln mit Signierstunde und Verleihung der Goldenen Taratze.',
        ]);
    }
}
