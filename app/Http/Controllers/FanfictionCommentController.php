<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Activity;
use App\Models\Fanfiction;
use App\Models\FanfictionComment;
use App\Models\Team;
use App\Services\UserRoleService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FanfictionCommentController extends Controller
{
    public function __construct(private readonly UserRoleService $userRoleService)
    {
    }

    /**
     * Liefert das Team "Mitglieder".
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
     * Speichert einen neuen Kommentar zu einer Fanfiction.
     */
    public function store(Request $request, Fanfiction $fanfiction): RedirectResponse
    {
        $user = Auth::user();
        $role = $this->getRoleInMemberTeam();

        if (! $role || ! in_array($role, [Role::Mitwirkender, Role::Mitglied, Role::Ehrenmitglied, Role::Kassenwart, Role::Vorstand, Role::Admin], true)) {
            abort(403);
        }

        $request->validate([
            'content' => 'required|string|min:1',
            'parent_id' => 'nullable|exists:fanfiction_comments,id',
        ]);

        $comment = $fanfiction->comments()->create([
            'user_id' => $user->id,
            'parent_id' => $request->parent_id,
            'content' => $request->content,
        ]);

        Activity::create([
            'user_id' => $user->id,
            'subject_type' => FanfictionComment::class,
            'subject_id' => $comment->id,
            'action' => 'created',
        ]);

        return back()->with('success', 'Kommentar erfolgreich gespeichert.');
    }

    /**
     * Aktualisiert einen vorhandenen Kommentar.
     */
    public function update(Request $request, FanfictionComment $comment): RedirectResponse
    {
        $user = Auth::user();

        if ($user->id !== $comment->user_id) {
            abort(403);
        }

        $validated = $request->validate([
            'content' => 'required|string|min:1',
        ]);

        $comment->update([
            'content' => $validated['content'],
        ]);

        return back()->with('success', 'Kommentar erfolgreich aktualisiert.');
    }

    /**
     * Löscht einen Kommentar.
     */
    public function destroy(FanfictionComment $comment): RedirectResponse
    {
        $user = Auth::user();
        $role = $this->getRoleInMemberTeam();

        if ($user->id !== $comment->user_id && ! in_array($role, [Role::Vorstand, Role::Admin], true)) {
            abort(403);
        }

        $comment->delete();

        return back()->with('success', 'Kommentar erfolgreich gelöscht.');
    }
}
