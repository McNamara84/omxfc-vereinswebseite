<?php

namespace App\Http\Controllers;

use App\Enums\KassenbuchEntryType;
use App\Models\KassenbuchEntry;
use App\Models\Kassenstand;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
        if (! $kassenstand) {
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
            $expiryDate = $user->bezahlt_bis instanceof Carbon
                ? $user->bezahlt_bis
                : Carbon::parse((string) $user->bezahlt_bis);
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
        $data = $request->validate([
            'bezahlt_bis' => 'required|date',
            'mitgliedsbeitrag' => 'required|numeric|min:0',
            'mitglied_seit' => 'nullable|date',
        ]);

        $currentUser = Auth::user();
        $team = $currentUser->currentTeam;

        // Prüfen, ob der aktuelle Benutzer die Rolle "Kassenwart" hat
        $userRole = $team->users()
            ->where('user_id', $currentUser->id)
            ->first()
            ->membership
            ->role;

        if (! in_array($userRole, ['Kassenwart', 'Admin'])) {
            return back()->with('error', 'Du hast keine Berechtigung, Zahlungsdaten zu aktualisieren.');
        }

        // Zahlungsdaten aktualisieren
        $user->update([
            'bezahlt_bis' => $data['bezahlt_bis'],
            'mitgliedsbeitrag' => $data['mitgliedsbeitrag'],
            'mitglied_seit' => $data['mitglied_seit'] ?? null,
        ]);

        return back()->with('status', 'Zahlungsdaten für '.$user->name.' wurden aktualisiert.');
    }

    public function addKassenbuchEntry(Request $request)
    {
        $data = $request->validate([
            'buchungsdatum' => 'required|date',
            'betrag' => 'required|numeric|not_in:0',
            'beschreibung' => 'required|string|max:255',
            'typ' => 'required|in:'.implode(',', KassenbuchEntryType::values()),
        ]);

        $user = Auth::user();
        $team = $user->currentTeam;

        // Prüfen, ob der aktuelle Benutzer die Rolle "Kassenwart" hat
        $userRole = $team->users()
            ->where('user_id', $user->id)
            ->first()
            ->membership
            ->role;

        if (! in_array($userRole, ['Kassenwart', 'Admin'])) {
            return back()->with('error', 'Du hast keine Berechtigung, Kassenbucheinträge hinzuzufügen.');
        }

        // Betrag anpassen (positiv für Einnahmen, negativ für Ausgaben)
        $amount = abs($data['betrag']);
        if ($data['typ'] === KassenbuchEntryType::Ausgabe->value) {
            $amount = -$amount;
        }

        // Neuen Eintrag erstellen
        DB::transaction(function () use ($team, $user, $data, $amount) {
            // Kassenbucheintrag erstellen
            KassenbuchEntry::create([
                'team_id' => $team->id,
                'created_by' => $user->id,
                'buchungsdatum' => $data['buchungsdatum'],
                'betrag' => $amount,
                'beschreibung' => $data['beschreibung'],
                'typ' => $data['typ'],
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
