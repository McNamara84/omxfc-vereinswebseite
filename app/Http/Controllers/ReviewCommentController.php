<?php

namespace App\Http\Controllers;

use App\Mail\ReviewCommentNotification;
use App\Models\Review;
use App\Models\ReviewComment;
use App\Models\Team;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ReviewCommentController extends Controller
{
    /**
     * Liefert das Team "Mitglieder".
     */
    protected function memberTeam(): Team
    {
        return Team::where('name', 'Mitglieder')->firstOrFail();
    }

    /**
     * Liest die Rolle des eingeloggten Nutzers im Team "Mitglieder" aus der Pivot-Tabelle.
     */
    protected function getRoleInMemberTeam(): ?string
    {
        $team = Team::where('name', 'Mitglieder')->first();
        if (! $team) {
            return null;
        }

        return DB::table('team_user')
            ->where('team_id', $team->id)
            ->where('user_id', Auth::id())
            ->value('role');
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

        if (! ($hasOwn || in_array($role, ['Ehrenmitglied', 'Vorstand'], true))) {
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

        if ($user->id !== $comment->user_id && ! in_array($role, ['Vorstand', 'Admin'], true)) {
            abort(403);
        }

        $comment->delete();

        return back()->with('success', 'Kommentar erfolgreich gelöscht.');
    }
}
