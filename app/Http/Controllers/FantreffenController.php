<?php

namespace App\Http\Controllers;

use App\Mail\FantreffenAnmeldungBestaetigung;
use App\Mail\FantreffenNeueAnmeldung;
use App\Models\FantreffenAnmeldung;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class FantreffenController extends Controller
{
    public function create()
    {
        $user = Auth::user();
        
        // T-Shirt Deadline aus zentraler Konfiguration laden
        $tshirtDeadline = Carbon::parse(config('services.fantreffen.tshirt_deadline'));
        $tshirtDeadlinePassed = now()->isAfter($tshirtDeadline);
        $daysUntilDeadline = $tshirtDeadlinePassed ? 0 : (int) now()->diffInDays($tshirtDeadline, false);
        
        // Formatiertes Datum für die Anzeige (z.B. "28. Februar 2026")
        $tshirtDeadlineFormatted = $tshirtDeadline->locale('de')->isoFormat('D. MMMM YYYY');
        
        // Hinweis: paymentAmount wird nur für Button-Text verwendet
        // Die tatsächliche Berechnung erfolgt in store() basierend auf Auswahl
        $paymentAmount = 0;
        
        return view('fantreffen.anmeldung', [
            'user' => $user,
            'tshirtDeadlinePassed' => $tshirtDeadlinePassed,
            'daysUntilDeadline' => $daysUntilDeadline,
            'tshirtDeadlineFormatted' => $tshirtDeadlineFormatted,
            'paymentAmount' => $paymentAmount,
        ]);
    }
    
    public function store(Request $request)
    {
        Log::info('Fantreffen Anmeldung: Form submission started', [
            'request_data' => $request->all(),
            'user_id' => Auth::id(),
        ]);
        
        // Validierung
        $rules = [
            'mobile' => 'nullable|string|max:50',
            'tshirt_bestellt' => 'boolean',
            'tshirt_groesse' => 'required_if:tshirt_bestellt,1|nullable|in:XS,S,M,L,XL,XXL,XXXL',
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
            'tshirt_groesse.required_if' => 'Bitte wähle eine T-Shirt-Größe aus.',
            'tshirt_groesse.in' => 'Bitte wähle eine gültige T-Shirt-Größe aus.',
        ];
        
        try {
            $validated = $request->validate($rules, $messages);
            Log::info('Fantreffen Anmeldung: Validation passed');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Fantreffen Anmeldung: Validation failed', ['errors' => $e->errors()]);
            throw $e;
        }
        
        // Daten vorbereiten
        if (Auth::check()) {
            $vorname = Auth::user()->vorname;
            $nachname = Auth::user()->nachname;
            $email = Auth::user()->email;
        } else {
            $vorname = $validated['vorname'];
            $nachname = $validated['nachname'];
            $email = $validated['email'];
        }
        
        $tshirtBestellt = $request->boolean('tshirt_bestellt');
        
        // T-Shirt-Deadline prüfen - Bestellung nach Deadline verhindern
        $tshirtDeadline = Carbon::parse(config('services.fantreffen.tshirt_deadline'));
        $tshirtDeadlinePassed = now()->isAfter($tshirtDeadline);
        
        if ($tshirtBestellt && $tshirtDeadlinePassed) {
            return back()
                ->withInput()
                ->withErrors(['tshirt_bestellt' => 'Die Deadline für T-Shirt-Bestellungen ist leider abgelaufen.']);
        }
        
        $tshirtGroesse = $tshirtBestellt ? $validated['tshirt_groesse'] : null;
        
        // Kosten berechnen
        $paymentAmount = 0;
        
        if (Auth::check()) {
            // Eingeloggte Mitglieder: Teilnahme kostenlos, nur T-Shirt 25€
            if ($tshirtBestellt) {
                $paymentAmount = 25;
            }
        } else {
            // Gäste: Teilnahmegebühr 5€ + optional T-Shirt 25€
            $paymentAmount = 5; // Basisgebühr für Gäste
            if ($tshirtBestellt) {
                $paymentAmount += 25; // T-Shirt-Preis
            }
        }
        
        try {
            // Anmeldung erstellen
            $anmeldung = FantreffenAnmeldung::create([
                'user_id' => Auth::id(),
                'vorname' => $vorname,
                'nachname' => $nachname,
                'email' => $email,
                'mobile' => $validated['mobile'] ?? null,
                'tshirt_bestellt' => $tshirtBestellt,
                'tshirt_groesse' => $tshirtGroesse,
                'payment_amount' => $paymentAmount,
                'payment_status' => $paymentAmount > 0 ? 'pending' : 'free',
                'ist_mitglied' => Auth::check(),
                'zahlungseingang' => false,
            ]);
            
            Log::info('Fantreffen Anmeldung: Registration created', ['id' => $anmeldung->id]);
            
            // E-Mails versenden
            try {
                Mail::to($email)->send(new FantreffenAnmeldungBestaetigung($anmeldung));
                Log::info('Fantreffen Anmeldung: Confirmation email sent to participant');
                
                Mail::to('vorstand@maddrax-fanclub.de')->send(new FantreffenNeueAnmeldung($anmeldung));
                Log::info('Fantreffen Anmeldung: Notification email sent to vorstand');
            } catch (\Exception $e) {
                Log::error('Fantreffen Anmeldung: Email sending failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Fehler beim E-Mail-Versand nicht durchreichen, Anmeldung ist bereits gespeichert
            }
            
            // Redirect zur Bestätigungsseite
            return redirect()->route('fantreffen.2026.bestaetigung', ['id' => $anmeldung->id])
                ->with('success', 'Deine Anmeldung wurde erfolgreich gespeichert!');
                
        } catch (\Exception $e) {
            Log::error('Fantreffen Anmeldung: Registration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return back()
                ->withInput()
                ->withErrors(['error' => 'Es ist ein Fehler aufgetreten. Bitte versuche es erneut.']);
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
