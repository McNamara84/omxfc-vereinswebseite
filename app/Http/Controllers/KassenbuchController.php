<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\KassenbuchEntry;
use App\Models\Kassenstand;
use Carbon\Carbon;

class KassenbuchController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $team = $user->currentTeam;
        
        // Benutzerrolle ermitteln
        $userRole = $team->users()
            ->where('user_id', $user->id)
            ->first()
            ->membership
            ->role;
            
        // Aktuellen Kassenstand abrufen
        $kassenstand = Kassenstand::where('team_id', $team->id)->first();
        
        // Falls noch kein Kassenstand existiert, einen initialen Eintrag erstellen
        if (!$kassenstand) {
            $kassenstand = Kassenstand::create([
                'team_id' => $team->id,
                'betrag' => 0.00,
                'letzte_aktualisierung' => now(),
            ]);
        }
        
        // Für Vorstand und Kassenwart: Alle Mitglieder mit ihren Zahlungsdaten abrufen
        $members = null;
        $kassenbuchEntries = null;
        
        if (in_array($userRole, ['Vorstand', 'Admin', 'Kassenwart'])) {
            $members = $team->users()
                ->wherePivotNotIn('role', ['Anwärter'])
                ->orderBy('bezahlt_bis')
                ->get();
                
            $kassenbuchEntries = KassenbuchEntry::where('team_id', $team->id)
                ->orderBy('buchungsdatum', 'desc')
                ->get();
        }
        
        // Für das angemeldete Mitglied: Eigene Zahlungsdaten abrufen
        $memberData = $user;
        
        // Prüfen, ob Mitgliedschaft bald abläuft (innerhalb eines Monats)
        $renewalWarning = false;
        if ($user->bezahlt_bis) {
            $today = Carbon::now();
            $expiryDate = Carbon::parse($user->bezahlt_bis);
            $daysUntilExpiry = $today->diffInDays($expiryDate, false);
            
            if ($daysUntilExpiry > 0 && $daysUntilExpiry <= 30) {
                $renewalWarning = true;
            }
        }
        
        return view('kassenbuch.index', [
            'userRole' => $userRole,
            'kassenstand' => $kassenstand,
            'members' => $members,
            'kassenbuchEntries' => $kassenbuchEntries,
            'memberData' => $memberData,
            'renewalWarning' => $renewalWarning,
        ]);
    }
    
    public function updatePaymentStatus(Request $request, User $user)
    {
        $request->validate([
            'bezahlt_bis' => 'required|date',
            'mitgliedsbeitrag' => 'required|numeric|min:0',
        ]);
        
        $currentUser = Auth::user();
        $team = $currentUser->currentTeam;
        
        // Prüfen, ob der aktuelle Benutzer die Rolle "Kassenwart" hat
        $userRole = $team->users()
            ->where('user_id', $currentUser->id)
            ->first()
            ->membership
            ->role;
            
        if (!in_array($userRole, ['Kassenwart', 'Admin'])) {
            return back()->with('error', 'Du hast keine Berechtigung, Zahlungsdaten zu aktualisieren.');
        }
        
        // Zahlungsdaten aktualisieren
        $user->update([
            'bezahlt_bis' => $request->bezahlt_bis,
            'mitgliedsbeitrag' => $request->mitgliedsbeitrag,
        ]);
        
        return back()->with('status', 'Zahlungsdaten für ' . $user->name . ' wurden aktualisiert.');
    }
    
    public function addKassenbuchEntry(Request $request)
    {
        $request->validate([
            'buchungsdatum' => 'required|date',
            'betrag' => 'required|numeric|not_in:0',
            'beschreibung' => 'required|string|max:255',
            'typ' => 'required|in:einnahme,ausgabe',
        ]);
        
        $user = Auth::user();
        $team = $user->currentTeam;
        
        // Prüfen, ob der aktuelle Benutzer die Rolle "Kassenwart" hat
        $userRole = $team->users()
            ->where('user_id', $user->id)
            ->first()
            ->membership
            ->role;
            
        if (!in_array($userRole, ['Kassenwart', 'Admin'])) {
            return back()->with('error', 'Du hast keine Berechtigung, Kassenbucheinträge hinzuzufügen.');
        }
        
        // Betrag anpassen (positiv für Einnahmen, negativ für Ausgaben)
        $amount = abs($request->betrag);
        if ($request->typ === 'ausgabe') {
            $amount = -$amount;
        }
        
        // Neuen Eintrag erstellen
        DB::transaction(function () use ($team, $user, $request, $amount) {
            // Kassenbucheintrag erstellen
            KassenbuchEntry::create([
                'team_id' => $team->id,
                'created_by' => $user->id,
                'buchungsdatum' => $request->buchungsdatum,
                'betrag' => $amount,
                'beschreibung' => $request->beschreibung,
                'typ' => $request->typ,
            ]);
            
            // Kassenstand aktualisieren
            $kassenstand = Kassenstand::where('team_id', $team->id)->first();
            $kassenstand->betrag += $amount;
            $kassenstand->letzte_aktualisierung = now();
            $kassenstand->save();
        });
        
        return back()->with('status', 'Kassenbucheintrag wurde hinzugefügt.');
    }
}
