<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\Role;
use App\Models\Team;
use App\Services\UserRoleService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;

/**
 * Trait für Controller, die mit dem Mitglieder-Team arbeiten.
 *
 * Konsolidiert die wiederholte Logik für:
 * - Zugriff auf das "Mitglieder"-Team
 * - Ermittlung der Benutzerrolle im Team
 * - Rollenbasierte Autorisierung
 *
 * @property-read UserRoleService $userRoleService
 */
trait MembersTeamAware
{
    /**
     * Liefert das Team „Mitglieder".
     */
    protected function memberTeam(): Team
    {
        return Team::membersTeam();
    }

    /**
     * Liest die Rolle des eingeloggten Nutzers im Team "Mitglieder" aus der Pivot-Tabelle.
     */
    protected function getRoleInMemberTeam(): ?Role
    {
        $team = Team::membersTeam();
        $user = Auth::user();

        if (! $team || ! $user) {
            return null;
        }

        try {
            return $this->userRoleService->getRole($user, $team);
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    /**
     * Prüft ob der User mindestens eine der erlaubten Rollen hat.
     * Wirft 403 wenn nicht autorisiert.
     *
     * @param  Role  ...$allowedRoles  Die erlaubten Rollen
     * @return Role Die tatsächliche Rolle des Benutzers
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function authorizeMinimumRole(Role ...$allowedRoles): Role
    {
        $role = $this->getRoleInMemberTeam();

        if (! $role || ! in_array($role, $allowedRoles, true)) {
            abort(403);
        }

        return $role;
    }

    /**
     * Standard-Autorisierung für den Mitgliederbereich (alle Rollen außer Anwärter).
     *
     * Erlaubt: Mitwirkender, Mitglied, Ehrenmitglied, Kassenwart, Vorstand, Admin
     *
     * @return Role Die tatsächliche Rolle des Benutzers
     */
    protected function authorizeMemberArea(): Role
    {
        return $this->authorizeMinimumRole(
            Role::Mitwirkender,
            Role::Mitglied,
            Role::Ehrenmitglied,
            Role::Kassenwart,
            Role::Vorstand,
            Role::Admin
        );
    }

    /**
     * Autorisierung für vollwertige Mitglieder (ohne Mitwirkende).
     *
     * Erlaubt: Mitglied, Ehrenmitglied, Kassenwart, Vorstand, Admin
     *
     * @return Role Die tatsächliche Rolle des Benutzers
     */
    protected function authorizeFullMember(): Role
    {
        return $this->authorizeMinimumRole(
            Role::Mitglied,
            Role::Ehrenmitglied,
            Role::Kassenwart,
            Role::Vorstand,
            Role::Admin
        );
    }

    /**
     * Autorisierung für Vorstand und Admin.
     *
     * @return Role Die tatsächliche Rolle des Benutzers
     */
    protected function authorizeVorstandOrAdmin(): Role
    {
        return $this->authorizeMinimumRole(
            Role::Vorstand,
            Role::Admin
        );
    }
}
