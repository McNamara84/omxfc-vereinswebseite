<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Review;
use Illuminate\Support\Facades\Auth;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

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
        if (!$team) {
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

        if (!($hasOwn || in_array($role, ['Ehrenmitglied', 'Vorstand'], true))) {
            abort(403);
        }

        $request->validate([
            'content' => 'required|string|min:1',
            'parent_id' => 'nullable|exists:review_comments,id',
        ]);

        $review->comments()->create([
            'user_id' => $user->id,
            'parent_id' => $request->parent_id,
            'content' => $request->content,
        ]);

        return back()->with('success', 'Kommentar erfolgreich gespeichert.');
    }
}
