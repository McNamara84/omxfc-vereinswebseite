<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MitgliederController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        // Sortierparameter auslesen
        $sortBy = $request->input('sort', 'nachname'); // Standardsortierung: Nachname
        $sortDir = $request->input('dir', 'asc'); // Standardrichtung: aufsteigend

        // Nur erlaubte Sortierfelder akzeptieren
        $allowedSortFields = ['nachname', 'role', 'mitgliedsbeitrag', 'mitglied_seit', 'last_activity'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'nachname';
        }

        // Sortierrichtung validieren
        if (!in_array($sortDir, ['asc', 'desc'])) {
            $sortDir = 'asc';
        }

        // Nur Nutzer mit Rollen außer "Anwärter" anzeigen
        $membersQuery = $team->users()
            ->wherePivotNotIn('role', ['Anwärter']);

        $filters = (array) $request->input('filters', []);

        // IDs aller aktuell aktiven Nutzer ermitteln
        $onlineUserIds = DB::table('sessions')
            ->where('last_activity', '>=', now()->subMinutes(5)->timestamp)
            ->pluck('user_id')
            ->toArray();

        // Filter anwenden (z. B. nur online)
        if (in_array('online', $filters)) {
            $membersQuery->whereIn('users.id', $onlineUserIds);
        }

        // Sortierung anwenden
        if ($sortBy === 'role') {
            // Nach Rolle sortieren (Pivot-Tabelle)
            $members = $membersQuery->orderByPivot('role', $sortDir)->get();
        } elseif ($sortBy === 'last_activity') {
            // Für die Sortierung nach letzter Aktivität zunächst alle Mitglieder abrufen
            $members = $membersQuery->get();
        } else {
            // Nach anderen Feldern sortieren
            $members = $membersQuery->orderBy($sortBy, $sortDir)->get();
        }

        // Letzte Aktivität für alle Nutzer ermitteln
        $lastActivities = DB::table('sessions')
            ->select('user_id', DB::raw('MAX(last_activity) as last_activity'))
            ->groupBy('user_id')
            ->pluck('last_activity', 'user_id');

        foreach ($members as $member) {
            $member->last_activity = $lastActivities[$member->id] ?? null;
        }

        // Sortierung nach letzter Aktivität anwenden
        if ($sortBy === 'last_activity') {
            $members = $members->sortBy('last_activity', SORT_REGULAR, $sortDir === 'desc')->values();
        }

        // Korrekte Ermittlung der Rolle des eingeloggten Nutzers
        $userRole = $team->users()
            ->where('user_id', $user->id)
            ->first()
            ->membership
            ->role;

        // Prüft, ob der aktuelle Benutzer erweiterte Rechte hat
        $allowedRoles = ['Kassenwart', 'Vorstand', 'Admin'];
        $canViewDetails = in_array($userRole, $allowedRoles);

        // Rollenrangfolge festlegen (höhere Zahl = höherer Rang)
        $roleRanks = [
            'Mitglied' => 1,
            'Ehrenmitglied' => 2,
            'Kassenwart' => 3,
            'Vorstand' => 4,
            'Admin' => 5
        ];

        // Aktuellen Rang des Users ermitteln
        $currentUserRank = $roleRanks[$userRole] ?? 0;

        return view('mitglieder.index', [
            'members' => $members,
            'canViewDetails' => $canViewDetails,
            'currentUser' => $user,
            'currentUserRank' => $currentUserRank,
            'roleRanks' => $roleRanks,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'filters' => $filters,
            'onlineUserIds' => $onlineUserIds
        ]);
    }

    public function changeRole(Request $request, User $user)
    {
        $request->validate([
            'role' => 'required|string|in:Mitglied,Ehrenmitglied,Kassenwart,Vorstand,Admin',
        ]);

        $currentUser = Auth::user();
        $team = $currentUser->currentTeam;

        // Korrekte Ermittlung der Rolle des eingeloggten Nutzers
        $currentUserRole = $team->users()
            ->where('user_id', $currentUser->id)
            ->first()
            ->membership
            ->role;

        // Rolle des zu ändernden Nutzers
        $memberRole = $team->users()
            ->where('user_id', $user->id)
            ->first()
            ->membership
            ->role;

        // Rollenrangfolge festlegen (höhere Zahl = höherer Rang)
        $roleRanks = [
            'Mitglied' => 1,
            'Ehrenmitglied' => 2,
            'Kassenwart' => 3,
            'Vorstand' => 4,
            'Admin' => 5
        ];

        $currentUserRank = $roleRanks[$currentUserRole] ?? 0;
        $memberRank = $roleRanks[$memberRole] ?? 0;
        $newRoleRank = $roleRanks[$request->role] ?? 0;

        // Prüfen, ob der aktuelle Nutzer die Berechtigung hat
        if ($currentUserRank <= $memberRank) {
            return back()->with('error', 'Du hast keine Berechtigung, die Rolle dieses Mitglieds zu ändern.');
        }

        // Prüfen, ob die neue Rolle nicht höher als die eigene Rolle ist
        if ($newRoleRank > $currentUserRank) {
            return back()->with('error', 'Du kannst keine Rolle vergeben, die höher als deine eigene ist.');
        }

        // Rolle des Mitglieds ändern
        $team->users()->updateExistingPivot($user->id, ['role' => $request->role]);

        return back()->with('status', 'Die Rolle von ' . $user->name . ' wurde zu ' . $request->role . ' geändert.');
    }

    public function removeMember(User $user)
    {
        $currentUser = Auth::user();
        $team = $currentUser->currentTeam;

        // Korrekte Ermittlung der Rolle des eingeloggten Nutzers
        $currentUserRole = $team->users()
            ->where('user_id', $currentUser->id)
            ->first()
            ->membership
            ->role;

        // Rolle des zu entfernenden Nutzers
        $memberRole = $team->users()
            ->where('user_id', $user->id)
            ->first()
            ->membership
            ->role;

        // Rollenrangfolge festlegen (höhere Zahl = höherer Rang)
        $roleRanks = [
            'Mitglied' => 1,
            'Ehrenmitglied' => 2,
            'Kassenwart' => 3,
            'Vorstand' => 4,
            'Admin' => 5
        ];

        // Prüfen, ob der aktuelle Nutzer die Berechtigung hat
        if (($roleRanks[$currentUserRole] ?? 0) <= ($roleRanks[$memberRole] ?? 0)) {
            return back()->with('error', 'Du hast keine Berechtigung, dieses Mitglied zu entfernen.');
        }

        // Prüfen, ob das Mitglied sich selbst entfernen will
        if ($currentUser->id === $user->id) {
            return back()->with('error', 'Du kannst deine eigene Mitgliedschaft nicht beenden.');
        }

        // Mitglied aus Team entfernen
        $team->users()->detach($user->id);

        // Nutzer löschen
        $user->delete();

        return back()->with('status', 'Die Mitgliedschaft wurde erfolgreich beendet.');
    }

    /**
     * Exportiert Mitgliederdaten als CSV-Datei.
     */
    public function exportCsv(Request $request)
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        // Überprüfen, ob der Benutzer berechtigt ist (Kassenwart, Vorstand oder Admin)
        $allowedRoles = ['Kassenwart', 'Vorstand', 'Admin'];
        $userRole = $team->users()
            ->where('user_id', $user->id)
            ->first()
            ->membership
            ->role;

        if (!in_array($userRole, $allowedRoles)) {
            return back()->with('error', 'Du hast keine Berechtigung zum Exportieren von Mitgliederdaten.');
        }

        // Felder validieren
        $request->validate([
            'export_fields' => 'required|array',
            'export_fields.*' => 'in:name,email,adresse,bezahlt_bis',
        ]);

        // Mitglieder abrufen (ohne Anwärter)
        $members = $team->users()
            ->wherePivotNotIn('role', ['Anwärter'])
            ->orderBy('nachname')
            ->get();

        // CSV-Datei generieren
        $filename = 'mitglieder-export-' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        // StreamedResponse verwenden, um Speicherverbrauch zu minimieren
        return new StreamedResponse(function () use ($members, $request) {
            // Output-Stream öffnen und BOM für Excel-UTF-8-Kompatibilität setzen
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Spaltenüberschriften
            $headers = [];
            if (in_array('name', $request->export_fields)) {
                $headers[] = 'Name';
                $headers[] = 'Vorname';
                $headers[] = 'Nachname';
            }
            if (in_array('email', $request->export_fields)) {
                $headers[] = 'E-Mail';
            }
            if (in_array('adresse', $request->export_fields)) {
                $headers[] = 'Straße';
                $headers[] = 'Hausnummer';
                $headers[] = 'PLZ';
                $headers[] = 'Stadt';
                $headers[] = 'Land';
            }
            if (in_array('bezahlt_bis', $request->export_fields)) {
                $headers[] = 'Bezahlt bis';
            }

            fputcsv($handle, $headers);

            // Daten schreiben
            foreach ($members as $member) {
                $row = [];

                if (in_array('name', $request->export_fields)) {
                    $row[] = $member->name;
                    $row[] = $member->vorname;
                    $row[] = $member->nachname;
                }
                if (in_array('email', $request->export_fields)) {
                    $row[] = $member->email;
                }
                if (in_array('adresse', $request->export_fields)) {
                    $row[] = $member->strasse;
                    $row[] = $member->hausnummer;
                    $row[] = $member->plz;
                    $row[] = $member->stadt;
                    $row[] = $member->land;
                }
                if (in_array('bezahlt_bis', $request->export_fields)) {
                    $row[] = $member->bezahlt_bis ? $member->bezahlt_bis->format('d.m.Y') : '';
                }

                fputcsv($handle, $row);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Gibt alle E-Mail-Adressen als Textliste zurück.
     */
    public function getAllEmails()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        // Überprüfen, ob der Benutzer berechtigt ist (Kassenwart, Vorstand oder Admin)
        $allowedRoles = ['Kassenwart', 'Vorstand', 'Admin'];
        $userRole = $team->users()
            ->where('user_id', $user->id)
            ->first()
            ->membership
            ->role;

        if (!in_array($userRole, $allowedRoles)) {
            return response()->json(['error' => 'Keine Berechtigung'], 403);
        }

        // E-Mail-Adressen abrufen
        $emails = $team->users()
            ->wherePivotNotIn('role', ['Anwärter'])
            ->pluck('email')
            ->implode('; ');

        return response()->json(['emails' => $emails]);
    }
}
