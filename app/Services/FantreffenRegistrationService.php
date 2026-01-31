<?php

namespace App\Services;

use App\Mail\FantreffenAnmeldungBestaetigung;
use App\Mail\FantreffenNeueAnmeldung;
use App\Models\Activity;
use App\Models\FantreffenAnmeldung;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Service für Fantreffen-Anmeldungen.
 *
 * Konsolidiert die Anmeldelogik aus FantreffenController und FantreffenAnmeldung Livewire-Komponente.
 */
class FantreffenRegistrationService
{
    public function __construct(
        private readonly FantreffenDeadlineService $deadlineService
    ) {}

    /**
     * Validierungsregeln für die Fantreffen-Anmeldung.
     *
     * @param  bool  $isAuthenticated  Ob der User eingeloggt ist
     */
    public function validationRules(bool $isAuthenticated): array
    {
        $rules = [
            'mobile' => 'nullable|string|max:50',
            'tshirt_bestellt' => 'boolean',
            'tshirt_groesse' => 'required_if:tshirt_bestellt,true|nullable|in:XS,S,M,L,XL,XXL,XXXL',
        ];

        if (! $isAuthenticated) {
            $rules['vorname'] = 'required|string|max:255';
            $rules['nachname'] = 'required|string|max:255';
            $rules['email'] = 'required|email|max:255';
        }

        return $rules;
    }

    /**
     * Validierungs-Fehlermeldungen (deutsch).
     */
    public function validationMessages(): array
    {
        return [
            'vorname.required' => 'Bitte gib deinen Vornamen an.',
            'nachname.required' => 'Bitte gib deinen Nachnamen an.',
            'email.required' => 'Bitte gib deine E-Mail-Adresse an.',
            'email.email' => 'Bitte gib eine gültige E-Mail-Adresse an.',
            'mobile.string' => 'Bitte gib eine gültige Telefonnummer an.',
            'tshirt_groesse.required_if' => 'Bitte wähle eine T-Shirt-Größe aus.',
            'tshirt_groesse.in' => 'Bitte wähle eine gültige T-Shirt-Größe aus.',
        ];
    }

    /**
     * Berechnet den Zahlungsbetrag basierend auf T-Shirt-Bestellung und Mitgliedsstatus.
     *
     * @param  bool  $tshirtBestellt  Ob ein T-Shirt bestellt wurde
     * @param  bool  $isAuthenticated  Ob der User eingeloggt (Mitglied) ist
     */
    public function calculatePaymentAmount(bool $tshirtBestellt, bool $isAuthenticated): float
    {
        $amount = 0.0;

        // Gäste zahlen Grundgebühr
        if (! $isAuthenticated) {
            $amount += FantreffenAnmeldung::GUEST_FEE;
        }

        // T-Shirt-Preis
        if ($tshirtBestellt) {
            $amount += FantreffenAnmeldung::TSHIRT_PRICE;
        }

        return $amount;
    }

    /**
     * Prüft ob T-Shirt-Bestellung noch möglich ist.
     */
    public function canOrderTshirt(): bool
    {
        return ! $this->deadlineService->isPassed();
    }

    /**
     * Holt die Deadline-Informationen.
     */
    public function getDeadlineInfo(): array
    {
        return $this->deadlineService->toArray();
    }

    /**
     * Registriert eine neue Fantreffen-Anmeldung.
     *
     * @param  array  $data  Die validierten Formulardaten
     * @param  User|null  $user  Der eingeloggte User (falls vorhanden)
     * @return FantreffenAnmeldung Die erstellte Anmeldung
     *
     * @throws \InvalidArgumentException wenn T-Shirt nach Deadline bestellt wird
     * @throws \RuntimeException wenn die Anmeldung nicht erstellt werden konnte
     */
    public function register(array $data, ?User $user = null): FantreffenAnmeldung
    {
        $tshirtBestellt = (bool) ($data['tshirt_bestellt'] ?? false);
        $isAuthenticated = $user !== null;

        Log::info('FantreffenRegistrationService: Starting registration', [
            'tshirt_bestellt' => $tshirtBestellt,
            'is_authenticated' => $isAuthenticated,
            'user_id' => $user?->id,
        ]);

        // T-Shirt-Deadline prüfen
        if ($tshirtBestellt && ! $this->canOrderTshirt()) {
            throw new \InvalidArgumentException(
                'Die Deadline für T-Shirt-Bestellungen ist leider abgelaufen.'
            );
        }

        // Daten aus User oder Request
        $vorname = $user?->vorname ?? $data['vorname'];
        $nachname = $user?->nachname ?? $data['nachname'];
        $email = $user?->email ?? $data['email'];

        // Zahlungsbetrag berechnen
        $paymentAmount = $this->calculatePaymentAmount($tshirtBestellt, $isAuthenticated);

        try {
            // Anmeldung erstellen
            $anmeldung = FantreffenAnmeldung::create([
                'user_id' => $user?->id,
                'vorname' => $vorname,
                'nachname' => $nachname,
                'email' => $email,
                'mobile' => $data['mobile'] ?? null,
                'tshirt_bestellt' => $tshirtBestellt,
                'tshirt_groesse' => $tshirtBestellt ? ($data['tshirt_groesse'] ?? null) : null,
                'payment_amount' => $paymentAmount,
                'payment_status' => $paymentAmount > 0 ? 'pending' : 'free',
                'ist_mitglied' => $isAuthenticated,
                'zahlungseingang' => false,
            ]);

            Log::info('FantreffenRegistrationService: Registration created', ['id' => $anmeldung->id]);

            // Activity Log (auch für Gäste, dann ohne user_id)
            Activity::create([
                'user_id' => $user?->id,
                'subject_type' => FantreffenAnmeldung::class,
                'subject_id' => $anmeldung->id,
                'action' => 'fantreffen_registered',
            ]);

            // Mails versenden
            $this->sendConfirmationMails($anmeldung);

            return $anmeldung;

        } catch (\Exception $e) {
            Log::error('FantreffenRegistrationService: Registration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new \RuntimeException(
                'Es ist ein Fehler aufgetreten. Bitte versuche es erneut.',
                previous: $e
            );
        }
    }

    /**
     * Versendet Bestätigungs-Mails.
     */
    protected function sendConfirmationMails(FantreffenAnmeldung $anmeldung): void
    {
        // Bestätigung an Teilnehmer
        try {
            Mail::to($anmeldung->registrant_email ?? $anmeldung->email)
                ->send(new FantreffenAnmeldungBestaetigung($anmeldung));
            Log::info('FantreffenRegistrationService: Confirmation email sent to participant');
        } catch (\Exception $e) {
            Log::error('FantreffenRegistrationService: Failed to send confirmation email', [
                'error' => $e->getMessage(),
            ]);
        }

        // Benachrichtigung an Vorstand
        try {
            Mail::to('vorstand@maddrax-fanclub.de')
                ->send(new FantreffenNeueAnmeldung($anmeldung));
            Log::info('FantreffenRegistrationService: Notification email sent to Vorstand');
        } catch (\Exception $e) {
            Log::error('FantreffenRegistrationService: Failed to send Vorstand notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
