<?php

namespace App\Http\Controllers;

use App\Models\FantreffenAnmeldung;
use App\Models\FantreffenVipAuthor;
use App\Services\FantreffenRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
            ]
        ));
    }

    public function store(Request $request)
    {
        Log::info('Fantreffen Anmeldung: Form submission started', [
            'request_data' => $request->all(),
            'user_id' => Auth::id(),
        ]);

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
