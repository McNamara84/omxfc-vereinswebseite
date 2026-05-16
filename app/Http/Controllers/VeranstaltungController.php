<?php

namespace App\Http\Controllers;

use App\Models\FantreffenAnmeldung;
use App\Models\Veranstaltung;
use App\Services\FantreffenRegistrationService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class VeranstaltungController extends Controller
{
    public function __construct(
        private readonly FantreffenRegistrationService $registrationService
    ) {}

    public function aktuell(): RedirectResponse
    {
        $veranstaltung = Veranstaltung::featuredPublic();

        abort_if($veranstaltung === null, 404);

        return redirect()->route('veranstaltungen.show', $veranstaltung);
    }

    public function legacyShow(): RedirectResponse
    {
        return redirect()->route('veranstaltungen.show', $this->legacyVeranstaltung(), 301);
    }

    public function legacyStore(Request $request)
    {
        return $this->store($request, $this->legacyVeranstaltung());
    }

    public function legacyBestaetigung(Request $request, int $id): RedirectResponse
    {
        if (! Auth::check()) {
            abort_unless($request->hasValidSignature(), 403, 'Der Bestätigungslink ist ungültig.');
        }

        $anmeldung = FantreffenAnmeldung::with('veranstaltung')->findOrFail($id);
        $veranstaltung = $anmeldung->veranstaltung ?? $this->legacyVeranstaltung();

        abort_if($veranstaltung === null, 404);

        return redirect()->to($this->confirmationUrl($veranstaltung, $anmeldung));
    }

    private function legacyVeranstaltung(): Veranstaltung
    {
        return Veranstaltung::query()
            ->oeffentlichSichtbar()
            ->where('slug', 'maddrax-fantreffen-2026')
            ->firstOrFail();
    }

    public function show(Veranstaltung $veranstaltung): View
    {
        abort_unless($veranstaltung->isPubliclyVisible(), 404);

        $user = Auth::user();
        $merchArtikel = $veranstaltung->merchartikel()
            ->aktiv()
            ->with([
                'varianten' => fn ($query) => $query->aktiv()->orderBy('sort_order')->orderBy('id'),
            ])
            ->get();
        $vipAuthors = $veranstaltung->vip_autoren_aktiv
            ? $veranstaltung->vipAutoren()->active()->ordered()->get()
            : collect();

        return view('veranstaltungen.show', array_merge(
            $this->registrationService->getDeadlineInfo($veranstaltung),
            [
                'veranstaltung' => $veranstaltung,
                'sections' => $veranstaltung->abschnitte()->sichtbar()->get(),
                'user' => $user,
                'merchArtikel' => $merchArtikel,
                'merchBestellbar' => $merchArtikel->isNotEmpty() && $this->registrationService->canOrderMerch($veranstaltung),
                'vipAuthors' => $vipAuthors,
                'formLoadedAt' => Crypt::encryptString((string) time()),
            ]
        ));
    }

    private function safeLog(string $level, string $message, array $context = []): void
    {
        try {
            Log::$level($message, $context);
        } catch (\Throwable) {
            // Logging darf den Request nicht abbrechen.
        }
    }

    public function store(Request $request, Veranstaltung $veranstaltung)
    {
        if (! $veranstaltung->isRegistrationOpen()) {
            return redirect()->route('veranstaltungen.show', $veranstaltung)
                ->withErrors(['error' => 'Die Anmeldung für diese Veranstaltung ist derzeit geschlossen.']);
        }

        $spamErrorMessage = 'Die Anmeldung konnte nicht verarbeitet werden. Bitte versuche es erneut.';

        $honeypotValue = $request->input('website');
        if ($honeypotValue !== null && $honeypotValue !== '') {
            $this->safeLog('warning', 'Veranstaltungsanmeldung: Honeypot triggered', [
                'veranstaltung_id' => $veranstaltung->id,
                'ip' => $request->ip(),
            ]);

            return redirect()->route('veranstaltungen.show', $veranstaltung)
                ->withErrors(['error' => $spamErrorMessage]);
        }

        $minFormTime = (int) config('services.fantreffen.min_form_time', 3);
        $formToken = $request->input('_form_token');

        if (! $formToken) {
            return redirect()->route('veranstaltungen.show', $veranstaltung)
                ->withErrors(['error' => $spamErrorMessage]);
        }

        if ($minFormTime > 0) {
            try {
                $loadedAt = (int) Crypt::decryptString($formToken);

                if (time() - $loadedAt < $minFormTime) {
                    return redirect()->route('veranstaltungen.show', $veranstaltung)
                        ->withErrors(['error' => $spamErrorMessage]);
                }
            } catch (DecryptException) {
                return redirect()->route('veranstaltungen.show', $veranstaltung)
                    ->withErrors(['error' => $spamErrorMessage]);
            }
        }

        if (Auth::check()) {
            $exists = FantreffenAnmeldung::query()
                ->where('veranstaltung_id', $veranstaltung->id)
                ->where('user_id', Auth::id())
                ->exists();

            if ($exists) {
                return back()->withErrors([
                    'email' => 'Du bist bereits für diese Veranstaltung angemeldet.',
                ])->withInput();
            }
        }

        $isAuthenticated = Auth::check();

        try {
            $validated = $request->validate(
                $this->registrationService->validationRules($isAuthenticated, $veranstaltung),
                $this->registrationService->validationMessages($veranstaltung)
            );
        } catch (ValidationException $e) {
            $this->safeLog('error', 'Veranstaltungsanmeldung: Validation failed', [
                'veranstaltung_id' => $veranstaltung->id,
                'errors' => $e->errors(),
            ]);

            throw $e;
        }

        try {
            $anmeldung = $this->registrationService->register($validated, Auth::user(), $veranstaltung);

            return redirect()->to($this->confirmationUrl($veranstaltung, $anmeldung))
                ->with('success', 'Deine Anmeldung wurde erfolgreich gespeichert!');
        } catch (ValidationException $e) {
            return back()
                ->withInput()
                ->withErrors($e->errors());
        } catch (\InvalidArgumentException $e) {
            return back()
                ->withInput()
                ->withErrors(['tshirt_bestellt' => $e->getMessage()]);
        } catch (\RuntimeException $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function bestaetigung(Request $request, Veranstaltung $veranstaltung, int $id): View
    {
        $anmeldung = FantreffenAnmeldung::query()
            ->where('veranstaltung_id', $veranstaltung->id)
            ->findOrFail($id);

        if (! Auth::check()) {
            abort_unless($request->hasValidSignature(), 403, 'Der Bestätigungslink ist ungültig.');
        }

        if (Auth::check() && $anmeldung->user_id && Auth::id() !== $anmeldung->user_id) {
            abort(403, 'Du bist nicht berechtigt, diese Anmeldung anzusehen.');
        }

        return view('veranstaltungen.bestaetigung', [
            'veranstaltung' => $veranstaltung,
            'anmeldung' => $anmeldung,
        ]);
    }

    private function confirmationUrl(Veranstaltung $veranstaltung, FantreffenAnmeldung $anmeldung): string
    {
        return URL::signedRoute('veranstaltungen.bestaetigung', [
            'veranstaltung' => $veranstaltung,
            'id' => $anmeldung->id,
        ]);
    }
}