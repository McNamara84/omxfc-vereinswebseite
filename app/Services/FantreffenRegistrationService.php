<?php

namespace App\Services;

use App\Mail\FantreffenAnmeldungBestaetigung;
use App\Mail\FantreffenNeueAnmeldung;
use App\Models\Activity;
use App\Models\FantreffenAnmeldung;
use App\Models\User;
use App\Models\Veranstaltung;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

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

    private function safeLog(string $level, string $message, array $context = []): void
    {
        try {
            Log::$level($message, $context);
        } catch (\Throwable) {
            // Logging darf die Registrierung nicht verhindern.
        }
    }

    /**
     * Validierungsregeln für die Fantreffen-Anmeldung.
     *
     * @param  bool  $isAuthenticated  Ob der User eingeloggt ist
     */
    public function validationRules(bool $isAuthenticated, ?Veranstaltung $veranstaltung = null): array
    {
        $tshirtAktiv = $veranstaltung?->tshirt_aktiv ?? true;

        $rules = [
            'website' => 'nullable',
            'mobile' => 'nullable|string|max:50',
            'tshirt_bestellt' => 'boolean',
            'tshirt_groesse' => $tshirtAktiv
                ? 'required_if:tshirt_bestellt,true|nullable|in:XS,S,M,L,XL,XXL,XXXL'
                : 'nullable|in:XS,S,M,L,XL,XXL,XXXL',
        ];

        if (! $isAuthenticated) {
            $rules['vorname'] = 'required|string|max:255';
            $rules['nachname'] = 'required|string|max:255';
            $rules['email'] = [
                'required',
                'email',
                'max:255',
                $veranstaltung
                    ? Rule::unique('fantreffen_anmeldungen', 'email')->where(fn ($query) => $query->where('veranstaltung_id', $veranstaltung->id))
                    : Rule::unique('fantreffen_anmeldungen', 'email'),
            ];
        }

        return $rules;
    }

    /**
     * Validierungs-Fehlermeldungen (deutsch).
     */
    public function validationMessages(?Veranstaltung $veranstaltung = null): array
    {
        $bezeichnung = $veranstaltung?->titel ?? 'diese Veranstaltung';

        return [
            'vorname.required' => 'Bitte gib deinen Vornamen an.',
            'nachname.required' => 'Bitte gib deinen Nachnamen an.',
            'email.required' => 'Bitte gib deine E-Mail-Adresse an.',
            'email.email' => 'Bitte gib eine gültige E-Mail-Adresse an.',
            'mobile.string' => 'Bitte gib eine gültige Telefonnummer an.',
            'tshirt_groesse.required_if' => 'Bitte wähle eine T-Shirt-Größe aus.',
            'tshirt_groesse.in' => 'Bitte wähle eine gültige T-Shirt-Größe aus.',
            'email.unique' => "Diese E-Mail-Adresse ist bereits für {$bezeichnung} angemeldet.",
        ];
    }

    /**
     * Berechnet den Zahlungsbetrag basierend auf T-Shirt-Bestellung und Mitgliedsstatus.
     *
     * @param  bool  $tshirtBestellt  Ob ein T-Shirt bestellt wurde
     * @param  bool  $isAuthenticated  Ob der User eingeloggt (Mitglied) ist
     */
    public function calculatePaymentAmount(bool $tshirtBestellt, bool $isAuthenticated, ?Veranstaltung $veranstaltung = null): float
    {
        if ($veranstaltung && ! $veranstaltung->zahlung_aktiv) {
            return 0.0;
        }

        $amount = 0.0;
        $guestFee = (float) ($veranstaltung?->gastgebuehr ?? FantreffenAnmeldung::GUEST_FEE);
        $tshirtPrice = (float) ($veranstaltung?->tshirt_preis ?? FantreffenAnmeldung::TSHIRT_PRICE);

        // Gäste zahlen Grundgebühr
        if (! $isAuthenticated) {
            $amount += $guestFee;
        }

        // T-Shirt-Preis
        if ($tshirtBestellt && ($veranstaltung?->tshirt_aktiv ?? true)) {
            $amount += $tshirtPrice;
        }

        return $amount;
    }

    /**
     * Prüft ob T-Shirt-Bestellung noch möglich ist.
     */
    public function canOrderTshirt(?Veranstaltung $veranstaltung = null): bool
    {
        if ($veranstaltung && ! $veranstaltung->tshirt_aktiv) {
            return false;
        }

        return ! $this->deadlineService->isPassed($veranstaltung);
    }

    /**
     * Holt die Deadline-Informationen.
     */
    public function getDeadlineInfo(?Veranstaltung $veranstaltung = null): array
    {
        return $this->deadlineService->toArray($veranstaltung);
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
    public function register(array $data, ?User $user = null, ?Veranstaltung $veranstaltung = null): FantreffenAnmeldung
    {
        $tshirtBestellt = (bool) ($data['tshirt_bestellt'] ?? false) && ($veranstaltung?->tshirt_aktiv ?? true);
        $isAuthenticated = $user !== null;

        $this->safeLog('info', 'FantreffenRegistrationService: Starting registration', [
            'tshirt_bestellt' => $tshirtBestellt,
            'is_authenticated' => $isAuthenticated,
            'user_id' => $user?->id,
        ]);

        // T-Shirt-Deadline prüfen
        if ($tshirtBestellt && ! $this->canOrderTshirt($veranstaltung)) {
            throw new \InvalidArgumentException(
                'Die Deadline für T-Shirt-Bestellungen ist leider abgelaufen.'
            );
        }

        // Daten aus User oder Request
        if ($isAuthenticated) {
            if (empty($user->vorname) || empty($user->nachname) || empty($user->email)) {
                throw new \InvalidArgumentException(
                    'Dein Benutzerprofil ist unvollständig. Bitte ergänze Vorname, Nachname und E-Mail in deinen Profileinstellungen.'
                );
            }
            $vorname = $user->vorname;
            $nachname = $user->nachname;
            $email = $user->email;
        } else {
            $vorname = $data['vorname'];
            $nachname = $data['nachname'];
            $email = $data['email'];
        }

        // Zahlungsbetrag berechnen
        $paymentAmount = $this->calculatePaymentAmount($tshirtBestellt, $isAuthenticated, $veranstaltung);

        try {
            // Anmeldung erstellen
            $anmeldung = FantreffenAnmeldung::create([
                'veranstaltung_id' => $veranstaltung?->id,
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

            $this->safeLog('info', 'FantreffenRegistrationService: Registration created', ['id' => $anmeldung->id]);

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
            $this->safeLog('error', 'FantreffenRegistrationService: Registration failed', [
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
        // Wir verwenden direkt $anmeldung->email, da dieses Feld in register()
        // bereits korrekt gesetzt wurde (User-Email oder Gast-Email)
        try {
            Mail::to($anmeldung->email)
                ->send(new FantreffenAnmeldungBestaetigung($anmeldung));
            $this->safeLog('info', 'FantreffenRegistrationService: Confirmation email sent to participant');
        } catch (\Exception $e) {
            $this->safeLog('error', 'FantreffenRegistrationService: Failed to send confirmation email', [
                'error' => $e->getMessage(),
            ]);
        }

        // Benachrichtigung an Vorstand
        try {
            Mail::to($anmeldung->veranstaltung?->kontaktEmail() ?? config('services.paypal.fantreffen_email', 'vorstand@maddrax-fanclub.de'))
                ->send(new FantreffenNeueAnmeldung($anmeldung));
            $this->safeLog('info', 'FantreffenRegistrationService: Notification email sent to Vorstand');
        } catch (\Exception $e) {
            $this->safeLog('error', 'FantreffenRegistrationService: Failed to send Vorstand notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
