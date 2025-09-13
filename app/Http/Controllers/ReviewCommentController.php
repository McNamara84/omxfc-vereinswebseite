<?php

namespace App\Http\Controllers;

use App\Mail\ReviewCommentNotification;
use App\Models\Review;
use App\Models\ReviewComment;
use App\Models\Team;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Enums\Role;
use App\Services\UserRoleService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ReviewCommentController extends Controller
{
    public function __construct(private UserRoleService $userRoleService)
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
     * Speichert einen neuen Kommentar zu einer Rezension.
     */
    public function store(Request $request, Review $review)
    {
        $user = Auth::user();
        $role = $this->getRoleInMemberTeam();

        $book = $review->book;
        $hasOwn = $book->reviews()
            ->where('team_id', $this->memberTeam()->id)
            ->where('user_id', $user->id)
            ->exists();

        if (! ($hasOwn || in_array($role, [Role::Ehrenmitglied, Role::Vorstand], true))) {
            abort(403);
        }

        $request->validate([
            'content' => 'required|string|min:1',
            'parent_id' => 'nullable|exists:review_comments,id',
        ]);

        $comment = $review->comments()->create([
            'user_id' => $user->id,
            'parent_id' => $request->parent_id,
            'content' => $request->content,
        ]);

        if ($review->user_id !== $user->id) {
            Mail::to($review->user->email)
                ->queue(new ReviewCommentNotification($review, $comment));
        }

        Activity::create([
            'user_id' => $user->id,
            'subject_type' => ReviewComment::class,
            'subject_id' => $comment->id,
        ]);

        return back()->with('success', 'Kommentar erfolgreich gespeichert.');
    }

    /**
     * Aktualisiert einen vorhandenen Kommentar.
     */
    public function update(Request $request, ReviewComment $comment)
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
    public function destroy(ReviewComment $comment)
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
