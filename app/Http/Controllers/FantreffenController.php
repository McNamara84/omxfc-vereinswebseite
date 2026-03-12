<?php

namespace App\Http\Controllers;

use App\Models\FantreffenAnmeldung;
use App\Models\FantreffenVipAuthor;
use App\Services\FantreffenRegistrationService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class FantreffenController extends Controller
{
    public function __construct(
        private readonly FantreffenRegistrationService $registrationService
    ) {}

    public function create()
    {
        $user = Auth::user();

        // VIP-Autoren laden (cached for 1 hour)
        $vipAuthors = Cache::remember('fantreffen_vip_authors', 3600, function () {
            return FantreffenVipAuthor::active()->ordered()->get();
        });

        // Hinweis: paymentAmount wird nur für Button-Text verwendet
        // Die tatsächliche Berechnung erfolgt in store() basierend auf Auswahl
        $paymentAmount = 0;

        return view('fantreffen.anmeldung', array_merge(
            $this->registrationService->getDeadlineInfo(),
            [
                'user' => $user,
                'paymentAmount' => $paymentAmount,
                'vipAuthors' => $vipAuthors,
                'formLoadedAt' => Crypt::encryptString((string) time()),
            ]
        ));
    }

    public function store(Request $request)
    {
        $spamErrorMessage = 'Die Anmeldung konnte nicht verarbeitet werden. Bitte versuche es erneut.';

        // Honeypot-Prüfung: Jeder nicht-leere Wert ist ein Bot.
        // TrimStrings-Middleware trimmt Whitespace → ' ' wird zu '' → kein False-Positive.
        // Validation nutzt nur 'nullable' (kein max:0), damit keine Fehlermeldung das Feld leakt.
        $honeypotValue = $request->input('website');
        if ($honeypotValue !== null && $honeypotValue !== '') {
            Log::warning('Fantreffen Anmeldung: Honeypot triggered', [
                'ip' => $request->ip(),
            ]);

            return redirect()->route('fantreffen.2026')
                ->withErrors(['error' => $spamErrorMessage]);
        }

        // Timing-Check: Formular muss mindestens N Sekunden alt sein
        $minFormTime = (int) config('services.fantreffen.min_form_time', 3);
        $formToken = $request->input('_form_token');

        if (! $formToken) {
            Log::warning('Fantreffen Anmeldung: Missing form token', [
                'ip' => $request->ip(),
            ]);

            return redirect()->route('fantreffen.2026')
                ->withErrors(['error' => $spamErrorMessage]);
        }

        if ($minFormTime > 0) {
            try {
                $loadedAt = (int) Crypt::decryptString($formToken);
                if (time() - $loadedAt < $minFormTime) {
                    Log::warning('Fantreffen Anmeldung: Timing check failed', [
                        'ip' => $request->ip(),
                        'elapsed_seconds' => time() - $loadedAt,
                    ]);

                    return redirect()->route('fantreffen.2026')
                        ->withErrors(['error' => $spamErrorMessage]);
                }
            } catch (DecryptException) {
                Log::warning('Fantreffen Anmeldung: Invalid form token', [
                    'ip' => $request->ip(),
                ]);

                return redirect()->route('fantreffen.2026')
                    ->withErrors(['error' => $spamErrorMessage]);
            }
        }

        Log::debug('Fantreffen Anmeldung: Form submission started', [
            'user_id' => Auth::id(),
            'is_authenticated' => Auth::check(),
        ]);

        // Duplikat-Prüfung für eingeloggte User
        if (Auth::check()) {
            $email = Auth::user()->email;
            if (FantreffenAnmeldung::where('email', $email)->exists()) {
                return back()->withErrors([
                    'email' => 'Du bist bereits für das Fantreffen 2026 angemeldet.',
                ])->withInput();
            }
        }

        // Validierung über Service
        $isAuthenticated = Auth::check();

        try {
            $validated = $request->validate(
                $this->registrationService->validationRules($isAuthenticated),
                $this->registrationService->validationMessages()
            );
            Log::info('Fantreffen Anmeldung: Validation passed');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Fantreffen Anmeldung: Validation failed', ['errors' => $e->errors()]);
            throw $e;
        }

        try {
            // Anmeldung über Service erstellen
            $anmeldung = $this->registrationService->register($validated, Auth::user());

            // Redirect zur Bestätigungsseite
            return redirect()->route('fantreffen.2026.bestaetigung', ['id' => $anmeldung->id])
                ->with('success', 'Deine Anmeldung wurde erfolgreich gespeichert!');

        } catch (\InvalidArgumentException $e) {
            // T-Shirt-Deadline abgelaufen
            return back()
                ->withInput()
                ->withErrors(['tshirt_bestellt' => $e->getMessage()]);

        } catch (\RuntimeException $e) {
            // Allgemeiner Fehler
            return back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function bestaetigung($id)
    {
        $anmeldung = FantreffenAnmeldung::findOrFail($id);

        // Optional: Berechtigungsprüfung für eingeloggte User
        // Gäste (nicht eingeloggt) dürfen die Bestätigungsseite sehen, wenn sie die ID haben
        // Eingeloggte User dürfen nur ihre eigene Anmeldung sehen (außer Admins)
        if (Auth::check() && $anmeldung->user_id && Auth::id() !== $anmeldung->user_id) {
            // Eingeloggter User versucht, eine fremde Anmeldung anzusehen
            // Hier könnte man eine Admin-Prüfung einbauen
            abort(403, 'Du bist nicht berechtigt, diese Anmeldung anzusehen.');
        }

        return view('fantreffen.bestaetigung', [
            'anmeldung' => $anmeldung,
        ]);
    }
}
