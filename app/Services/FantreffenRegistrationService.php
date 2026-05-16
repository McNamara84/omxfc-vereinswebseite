<?php

namespace App\Services;

use App\Mail\FantreffenAnmeldungBestaetigung;
use App\Mail\FantreffenNeueAnmeldung;
use App\Models\Activity;
use App\Models\FantreffenAnmeldung;
use App\Models\User;
use App\Models\Veranstaltung;
use App\Models\VeranstaltungsMerchartikel;
use App\Models\VeranstaltungsMerchvariante;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        $rules = [
            'website' => 'nullable',
            'mobile' => 'nullable|string|max:50',
            'merch' => 'nullable|array',
            'merch.*.selected' => 'nullable|boolean',
            'merch.*.variant_id' => 'nullable|integer',
            'tshirt_bestellt' => 'boolean',
            'tshirt_groesse' => 'required_if:tshirt_bestellt,true|nullable|string|max:50',
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
            'merch.*.variant_id.integer' => 'Bitte wähle eine gültige Variante aus.',
            'tshirt_groesse.required_if' => 'Bitte wähle eine T-Shirt-Größe aus.',
            'email.unique' => "Diese E-Mail-Adresse ist bereits für {$bezeichnung} angemeldet.",
        ];
    }

    /**
     * Berechnet den Zahlungsbetrag basierend auf Merchandise-Auswahl und Mitgliedsstatus.
     *
     * @param  bool|array  $merchSelection  Legacy-T-Shirt-Flag oder bereits normalisierte Merchandise-Bestellungen
     * @param  bool  $isAuthenticated  Ob der User eingeloggt (Mitglied) ist
     */
    public function calculatePaymentAmount(bool|array $merchSelection, bool $isAuthenticated, ?Veranstaltung $veranstaltung = null): float
    {
        $amount = 0.0;

        // Gäste zahlen Grundgebühr
        if (! $isAuthenticated && ($veranstaltung?->zahlung_aktiv ?? true)) {
            $amount += (float) ($veranstaltung?->gastgebuehr ?? FantreffenAnmeldung::GUEST_FEE);
        }

        $amount += $this->calculateMerchandiseTotal($merchSelection, $veranstaltung);

        return $amount;
    }

    /**
     * Prüft ob Merchandise-Bestellung noch möglich ist.
     */
    public function canOrderMerch(?Veranstaltung $veranstaltung = null): bool
    {
        if ($veranstaltung && $this->orderableMerchArtikel($veranstaltung)->isEmpty()) {
            return false;
        }

        return ! $this->deadlineService->isPassed($veranstaltung);
    }

    public function canOrderTshirt(?Veranstaltung $veranstaltung = null): bool
    {
        return $this->canOrderMerch($veranstaltung);
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
      * @throws \InvalidArgumentException wenn das Benutzerprofil eines eingeloggten Mitglieds unvollständig ist
      * @throws \Illuminate\Validation\ValidationException wenn Merchandise-Auswahl oder Varianten ungültig sind
     * @throws \RuntimeException wenn die Anmeldung nicht erstellt werden konnte
     */
    public function register(array $data, ?User $user = null, ?Veranstaltung $veranstaltung = null): FantreffenAnmeldung
    {
        $selectedMerchArtikel = $this->normalizeSelectedMerchArtikel($data, $veranstaltung);
        $isAuthenticated = $user !== null;

        $this->safeLog('info', 'FantreffenRegistrationService: Starting registration', [
            'merch_count' => count($selectedMerchArtikel),
            'is_authenticated' => $isAuthenticated,
            'user_id' => $user?->id,
        ]);

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
        $paymentAmount = $this->calculatePaymentAmount($selectedMerchArtikel, $isAuthenticated, $veranstaltung);

        try {
            $anmeldung = DB::transaction(function () use (
                $data,
                $email,
                $isAuthenticated,
                $paymentAmount,
                $selectedMerchArtikel,
                $user,
                $veranstaltung,
                $vorname,
                $nachname
            ) {
                $legacyTshirtBestellung = collect($selectedMerchArtikel)->first(
                    fn (array $bestellung) => $this->isLegacyTshirtArtikel($bestellung['artikel'])
                );
                $legacyTshirtVariante = $legacyTshirtBestellung['variante'] ?? null;

                $anmeldung = FantreffenAnmeldung::create([
                    'veranstaltung_id' => $veranstaltung?->id,
                    'user_id' => $user?->id,
                    'vorname' => $vorname,
                    'nachname' => $nachname,
                    'email' => $email,
                    'mobile' => $data['mobile'] ?? null,
                    'tshirt_bestellt' => $legacyTshirtBestellung !== null,
                    'tshirt_groesse' => $legacyTshirtVariante?->bezeichnung,
                    'payment_amount' => $paymentAmount,
                    'payment_status' => $paymentAmount > 0 ? 'pending' : 'free',
                    'ist_mitglied' => $isAuthenticated,
                    'zahlungseingang' => $paymentAmount === 0,
                ]);

                foreach ($selectedMerchArtikel as $bestellung) {
                    $anmeldung->merchartikelBestellungen()->create([
                        'veranstaltungs_merchartikel_id' => $bestellung['artikel']->id,
                        'veranstaltungs_merchvariante_id' => $bestellung['variante']?->id,
                        'preis_zum_bestellzeitpunkt' => $bestellung['price'],
                        'status_erledigt' => false,
                        'status_erledigt_am' => null,
                    ]);
                }

                Activity::create([
                    'user_id' => $user?->id,
                    'subject_type' => FantreffenAnmeldung::class,
                    'subject_id' => $anmeldung->id,
                    'action' => 'fantreffen_registered',
                ]);

                return $anmeldung;
            });

            $anmeldung->loadMissing([
                'veranstaltung',
                'merchartikelBestellungen.artikel',
                'merchartikelBestellungen.variante',
            ]);

            $this->safeLog('info', 'FantreffenRegistrationService: Registration created', ['id' => $anmeldung->id]);

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
        $anmeldung->loadMissing([
            'veranstaltung',
            'merchartikelBestellungen.artikel',
            'merchartikelBestellungen.variante',
        ]);

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

    private function orderableMerchArtikel(?Veranstaltung $veranstaltung = null): Collection
    {
        if (! $veranstaltung) {
            return collect();
        }

        return $veranstaltung->merchartikel()
            ->aktiv()
            ->with([
                'varianten' => fn ($query) => $query->aktiv()->orderBy('sort_order')->orderBy('id'),
            ])
            ->get();
    }

    private function normalizeSelectedMerchArtikel(array $data, ?Veranstaltung $veranstaltung = null): array
    {
        $orderableArtikel = $this->orderableMerchArtikel($veranstaltung)->keyBy('id');
        $submittedMerch = $this->submittedMerchData($data, $orderableArtikel);

        if ($orderableArtikel->isEmpty()) {
            if (($data['tshirt_bestellt'] ?? false) || ! empty($submittedMerch)) {
                throw ValidationException::withMessages([
                    'merch' => 'Für diese Veranstaltung kann aktuell kein Merchandise bestellt werden.',
                    'tshirt_bestellt' => 'Für diese Veranstaltung kann aktuell kein Merchandise bestellt werden.',
                ]);
            }

            return [];
        }

        $canOrderMerch = ! $this->deadlineService->isPassed($veranstaltung);
        $selectedArtikel = [];

        foreach ($orderableArtikel as $artikel) {
            $isSelected = $this->isTruthy(data_get($submittedMerch, $artikel->id.'.selected', false));

            if (! $isSelected) {
                continue;
            }

            if (! $canOrderMerch) {
                throw ValidationException::withMessages([
                    'merch' => 'Die Bestellfrist für Merchandise ist leider abgelaufen.',
                    'tshirt_bestellt' => 'Die Bestellfrist für Merchandise ist leider abgelaufen.',
                ]);
            }

            $variante = $this->resolveVariante($artikel, data_get($submittedMerch, $artikel->id.'.variant_id'));

            if ($artikel->requiresVariant() && ! $variante) {
                $errors = [
                    'merch.'.$artikel->id.'.variant_id' => 'Bitte wähle eine gültige Variante für '.$artikel->bezeichnung.' aus.',
                ];

                if ($this->isLegacyTshirtArtikel($artikel)) {
                    $errors['tshirt_groesse'] = 'Bitte wähle eine T-Shirt-Größe aus.';
                }

                throw ValidationException::withMessages($errors);
            }

            if (! $artikel->requiresVariant() && data_get($submittedMerch, $artikel->id.'.variant_id') && ! $variante) {
                throw ValidationException::withMessages([
                    'merch.'.$artikel->id.'.variant_id' => 'Bitte wähle eine gültige Variante für '.$artikel->bezeichnung.' aus.',
                ]);
            }

            $selectedArtikel[] = [
                'artikel' => $artikel,
                'variante' => $variante,
                'price' => (float) $artikel->preis,
            ];
        }

        return $selectedArtikel;
    }

    private function submittedMerchData(array $data, Collection $orderableArtikel): array
    {
        $submittedMerch = is_array($data['merch'] ?? null) ? $data['merch'] : [];

        if (! $this->isTruthy($data['tshirt_bestellt'] ?? false)) {
            return $submittedMerch;
        }

        $legacyArtikel = $orderableArtikel->first(
            fn (VeranstaltungsMerchartikel $artikel) => $this->isLegacyTshirtArtikel($artikel)
        );

        if (! $legacyArtikel) {
            return $submittedMerch;
        }

        $submittedMerch[$legacyArtikel->id] = [
            'selected' => true,
            'variant_id' => $data['tshirt_groesse'] ?? null,
        ];

        return $submittedMerch;
    }

    private function resolveVariante(VeranstaltungsMerchartikel $artikel, mixed $submittedVariant): ?VeranstaltungsMerchvariante
    {
        if ($submittedVariant === null || $submittedVariant === '') {
            return null;
        }

        if (! is_scalar($submittedVariant)) {
            return null;
        }

        $varianten = $artikel->varianten;

        if (is_numeric($submittedVariant)) {
            return $varianten->firstWhere('id', (int) $submittedVariant);
        }

        return $varianten->first(
            fn (VeranstaltungsMerchvariante $variante) => $variante->bezeichnung === (string) $submittedVariant
        );
    }

    private function calculateMerchandiseTotal(bool|array $merchSelection, ?Veranstaltung $veranstaltung = null): float
    {
        if (is_bool($merchSelection)) {
            if (! $merchSelection) {
                return 0.0;
            }

            return (float) ($veranstaltung?->tshirt_preis ?? FantreffenAnmeldung::TSHIRT_PRICE);
        }

        return (float) collect($merchSelection)->sum('price');
    }

    private function isTruthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'on'], true);
    }

    private function isLegacyTshirtArtikel(VeranstaltungsMerchartikel $artikel): bool
    {
        return mb_strtolower($artikel->bezeichnung) === 't-shirt';
    }
}
