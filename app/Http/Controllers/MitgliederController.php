<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Enums\Role;
use Illuminate\Validation\Rule;

class MitgliederController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        // Sortierparameter auslesen
        $sortBy = $request->input('sort', 'nachname'); // Standardsortierung: Nachname

        // Nur erlaubte Sortierfelder akzeptieren
        $allowedSortFields = ['nachname', 'role', 'mitgliedsbeitrag', 'mitglied_seit', 'last_activity'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'nachname';
        }

        // Standardrichtung nach Validierung bestimmen
        $defaultSortDir = $sortBy === 'last_activity' ? 'desc' : 'asc';
        $sortDir = $request->input('dir', $defaultSortDir);

        // Sortierrichtung validieren
        if (!in_array($sortDir, ['asc', 'desc'])) {
            $sortDir = $defaultSortDir;
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
        } else {
            // Nach anderen Feldern sortieren
            $members = $membersQuery->orderBy($sortBy, $sortDir)->get();
        }

        // Korrekte Ermittlung der Rolle des eingeloggten Nutzers
        $userRole = Role::from(
            $team->users()
                ->where('user_id', $user->id)
                ->first()
                ->membership
                ->role
        );

        // Prüft, ob der aktuelle Benutzer erweiterte Rechte hat
        $allowedRoles = [Role::Kassenwart, Role::Vorstand, Role::Admin];
        $canViewDetails = in_array($userRole, $allowedRoles, true);

        // Rollenrangfolge festlegen (höhere Zahl = höherer Rang)
        $roleRanks = [
            Role::Mitglied->value => 1,
            Role::Ehrenmitglied->value => 2,
            Role::Kassenwart->value => 3,
            Role::Vorstand->value => 4,
            Role::Admin->value => 5,
        ];

        // Aktuellen Rang des Users ermitteln
        $currentUserRank = $roleRanks[$userRole->value] ?? 0;

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
            'role' => ['required', 'string', Rule::in(array_map(fn(Role $r) => $r->value, Role::cases()))],
        ]);

        $currentUser = Auth::user();
        $team = $currentUser->currentTeam;

        // Korrekte Ermittlung der Rolle des eingeloggten Nutzers
        $currentUserRole = Role::from(
            $team->users()
                ->where('user_id', $currentUser->id)
                ->first()
                ->membership
                ->role
        );

        // Rolle des zu ändernden Nutzers
        $memberRole = Role::from(
            $team->users()
                ->where('user_id', $user->id)
                ->first()
                ->membership
                ->role
        );

        // Rollenrangfolge festlegen (höhere Zahl = höherer Rang)
        $roleRanks = [
            Role::Mitglied->value => 1,
            Role::Ehrenmitglied->value => 2,
            Role::Kassenwart->value => 3,
            Role::Vorstand->value => 4,
            Role::Admin->value => 5,
        ];

        $currentUserRank = $roleRanks[$currentUserRole->value] ?? 0;
        $memberRank = $roleRanks[$memberRole->value] ?? 0;
        $newRole = Role::from($request->role);
        $newRoleRank = $roleRanks[$newRole->value] ?? 0;

        // Prüfen, ob der aktuelle Nutzer die Berechtigung hat
        if ($currentUserRank <= $memberRank) {
            return back()->with('error', 'Du hast keine Berechtigung, die Rolle dieses Mitglieds zu ändern.');
        }

        // Prüfen, ob die neue Rolle nicht höher als die eigene Rolle ist
        if ($newRoleRank > $currentUserRank) {
            return back()->with('error', 'Du kannst keine Rolle vergeben, die höher als deine eigene ist.');
        }

        // Rolle des Mitglieds ändern
        $team->users()->updateExistingPivot($user->id, ['role' => $newRole->value]);

        return back()->with('status', 'Die Rolle von ' . $user->name . ' wurde zu ' . $request->role . ' geändert.');
    }

    public function removeMember(User $user)
    {
        $currentUser = Auth::user();
        $team = $currentUser->currentTeam;

        // Korrekte Ermittlung der Rolle des eingeloggten Nutzers
        $currentUserRole = Role::from(
            $team->users()
                ->where('user_id', $currentUser->id)
                ->first()
                ->membership
                ->role
        );

        // Rolle des zu entfernenden Nutzers
        $memberRole = Role::from(
            $team->users()
                ->where('user_id', $user->id)
                ->first()
                ->membership
                ->role
        );

        // Rollenrangfolge festlegen (höhere Zahl = höherer Rang)
        $roleRanks = [
            Role::Mitglied->value => 1,
            Role::Ehrenmitglied->value => 2,
            Role::Kassenwart->value => 3,
            Role::Vorstand->value => 4,
            Role::Admin->value => 5,
        ];

        // Prüfen, ob der aktuelle Nutzer die Berechtigung hat
        if (($roleRanks[$currentUserRole->value] ?? 0) <= ($roleRanks[$memberRole->value] ?? 0)) {
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
        $allowedRoles = [Role::Kassenwart, Role::Vorstand, Role::Admin];
        $userRole = Role::from(
            $team->users()
                ->where('user_id', $user->id)
                ->first()
                ->membership
                ->role
        );

        if (! in_array($userRole, $allowedRoles, true)) {
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
        $allowedRoles = [Role::Kassenwart, Role::Vorstand, Role::Admin];
        $userRole = Role::from(
            $team->users()
                ->where('user_id', $user->id)
                ->first()
                ->membership
                ->role
        );

        if (! in_array($userRole, $allowedRoles, true)) {
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
