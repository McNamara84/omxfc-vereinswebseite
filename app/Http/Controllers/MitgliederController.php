<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use App\Services\MembersTeamProvider;
use App\Services\UserRoleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MitgliederController extends Controller
{
    public function __construct(
        private UserRoleService $userRoleService,
        private MembersTeamProvider $membersTeamProvider
    ) {}

    public function changeRole(Request $request, User $user)
    {
        $request->validate([
            'role' => ['required', 'string', Rule::in(array_map(fn (Role $r) => $r->value, Role::cases()))],
        ]);

        $currentUser = Auth::user();
        $team = $this->membersTeamProvider->getMembersTeamOrAbort();

        $this->authorize('manage', User::class);

        // Korrekte Ermittlung der Rolle des eingeloggten Nutzers
        $currentUserRole = $this->userRoleService->getRole($currentUser, $team);

        // Rolle des zu ändernden Nutzers
        $memberRole = $this->userRoleService->getRole($user, $team);

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

        return back()->with('status', 'Die Rolle von '.$user->name.' wurde zu '.$request->role.' geändert.');
    }

    public function removeMember(User $user)
    {
        $currentUser = Auth::user();
        $team = $this->membersTeamProvider->getMembersTeamOrAbort();

        $this->authorize('manage', User::class);

        // Korrekte Ermittlung der Rolle des eingeloggten Nutzers
        $currentUserRole = $this->userRoleService->getRole($currentUser, $team);

        // Rolle des zu entfernenden Nutzers
        $memberRole = $this->userRoleService->getRole($user, $team);

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
        $team = $this->membersTeamProvider->getMembersTeamOrAbort();

        $this->authorize('manage', User::class);

        // Felder validieren
        $request->validate([
            'export_fields' => 'required|array',
            'export_fields.*' => 'in:name,email,adresse,bezahlt_bis',
        ]);

        // Mitglieder abrufen (ohne Anwärter)
        $members = $team->activeUsers()
            ->orderBy('nachname')
            ->get();

        // CSV-Datei generieren
        $filename = 'mitglieder-export-'.date('Y-m-d').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        // StreamedResponse verwenden, um Speicherverbrauch zu minimieren
        return new StreamedResponse(function () use ($members, $request) {
            // Output-Stream öffnen und BOM für Excel-UTF-8-Kompatibilität setzen
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

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
        $team = $this->membersTeamProvider->getMembersTeamOrAbort();

        $this->authorize('manage', User::class);

        // E-Mail-Adressen abrufen
        $emails = $team->activeUsers()
            ->pluck('email')
            ->implode('; ');

        return response()->json(['emails' => $emails]);
    }
}
