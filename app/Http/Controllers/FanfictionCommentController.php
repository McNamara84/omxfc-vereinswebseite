<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Controllers\Concerns\MembersTeamAware;
use App\Models\Activity;
use App\Models\Fanfiction;
use App\Models\FanfictionComment;
use App\Services\RewardService;
use App\Services\UserRoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FanfictionCommentController extends Controller
{
    use MembersTeamAware;

    public function __construct(
        private readonly UserRoleService $userRoleService,
        private readonly RewardService $rewardService,
    ) {}

    protected function getUserRoleService(): UserRoleService
    {
        return $this->userRoleService;
    }

    /**
     * Speichert einen neuen Kommentar zu einer Fanfiction.
     */
    public function store(Request $request, Fanfiction $fanfiction): RedirectResponse
    {
        $user = Auth::user();
        $role = $this->authorizeMemberArea();

        // Team-Scoping: Fanfiction muss zum Mitglieder-Team gehören
        if ($fanfiction->team_id !== $this->memberTeam()->id) {
            abort(404);
        }

        // Entwürfe dürfen nur Vorstand/Admin kommentieren
        if ($fanfiction->status !== \App\Enums\FanfictionStatus::Published
            && ! in_array($role, [Role::Vorstand, Role::Admin], true)) {
            abort(404);
        }

        // Prüfe ob die Fanfiction freigeschaltet wurde
        if ($fanfiction->reward && ! $this->rewardService->hasUnlockedRewardId($user, $fanfiction->reward->id)) {
            return redirect()->route('fanfiction.show', $fanfiction)
                ->withErrors(['reward' => 'Du musst diese Fanfiction zuerst freischalten, um kommentieren zu können.']);
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
