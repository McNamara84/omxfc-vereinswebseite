<?php

namespace App\Http\Controllers;

use App\Enums\FanfictionStatus;
use App\Enums\Role;
use App\Models\Fanfiction;
use App\Models\Team;
use App\Services\UserRoleService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FanfictionController extends Controller
{
    public function __construct(private readonly UserRoleService $userRoleService)
    {
    }

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
     * Öffentliche Ansicht für Gäste (Teaser).
     */
    public function publicIndex(): View
    {
        $fanfictions = Fanfiction::with('author')
            ->published()
            ->forTeam($this->memberTeam()->id)
            ->orderByDesc('published_at')
            ->paginate(12);

        return view('fanfiction.public-index', [
            'fanfictions' => $fanfictions,
        ]);
    }

    /**
     * Übersicht für eingeloggte Mitglieder.
     */
    public function index(Request $request): View
    {
        $role = $this->getRoleInMemberTeam();
        if (! $role || ! in_array($role, [Role::Mitglied, Role::Ehrenmitglied, Role::Kassenwart, Role::Vorstand, Role::Admin], true)) {
            abort(403);
        }

        $query = Fanfiction::with(['author', 'comments'])
            ->published()
            ->forTeam($this->memberTeam()->id);

        // Optional: Filter by author
        if ($request->filled('author')) {
            $query->where('user_id', $request->input('author'));
        }

        $fanfictions = $query->orderByDesc('published_at')->paginate(15);

        return view('fanfiction.index', [
            'fanfictions' => $fanfictions,
            'role' => $role,
        ]);
    }

    /**
     * Einzelansicht einer Fanfiction mit Kommentaren.
     */
    public function show(Fanfiction $fanfiction): View
    {
        $role = $this->getRoleInMemberTeam();
        if (! $role || ! in_array($role, [Role::Mitglied, Role::Ehrenmitglied, Role::Kassenwart, Role::Vorstand, Role::Admin], true)) {
            abort(403);
        }

        // Ensure fanfiction is published (unless user is Vorstand/Admin)
        if ($fanfiction->status !== FanfictionStatus::Published && ! in_array($role, [Role::Vorstand, Role::Admin], true)) {
            abort(404);
        }

        $fanfiction->load(['author', 'comments.user', 'comments.children.user']);

        return view('fanfiction.show', [
            'fanfiction' => $fanfiction,
            'role' => $role,
        ]);
    }
}
