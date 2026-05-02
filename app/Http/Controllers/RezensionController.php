<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Controllers\Concerns\MembersTeamAware;
use App\Http\Requests\ReviewRequest;
use App\Mail\NewReviewNotification;
use App\Models\Activity;
use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use App\Services\ReviewBaxxService;
use App\Services\UserRoleService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class RezensionController extends Controller
{
    use MembersTeamAware;

    public function __construct(
        private UserRoleService $userRoleService,
        private ReviewBaxxService $reviewBaxxService,
    ) {}

    protected function getUserRoleService(): UserRoleService
    {
        return $this->userRoleService;
    }

    /**
     * Speichern einer neuen Rezension.
     */
    public function store(ReviewRequest $request, Book $book)
    {
        $user = Auth::user();
        $role = $this->getRoleInMemberTeam();
        $teamId = $this->memberTeam()->id;

        $hasOwn = $book->reviews()
            ->where('team_id', $teamId)
            ->where('user_id', $user->id)
            ->exists();

        if ($hasOwn || ! in_array($role, [Role::Mitglied, Role::Vorstand, Role::Kassenwart, Role::Admin], true)) {
            abort(403);
        }

        $data = $request->validated();

        $review = Review::create([
            'team_id' => $teamId,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'title' => $data['title'],
            'content' => $data['content'],
        ]);

        // Award Baxx using the currently effective review rule.
        $reviewCount = Review::where('team_id', $teamId)
            ->where('user_id', $user->id)
            ->count();
        $this->reviewBaxxService->awardPointsForReview($user, $reviewCount, $teamId);

        // Autoren des Romans über neue Rezension informieren
        $authorNames = array_map('trim', explode(',', $book->author));
        $authors = User::whereIn('name', $authorNames)->get();
        foreach ($authors as $author) {
            if ($author->notify_new_review) {
                Mail::to($author->email)
                    ->queue(new NewReviewNotification($review, $author));
            }
        }

        Activity::create([
            'user_id' => $user->id,
            'subject_type' => Review::class,
            'subject_id' => $review->id,
        ]);

        return redirect()
            ->route('reviews.show', $book)
            ->with('success', 'Rezension erfolgreich erstellt.');
    }

    /**
     * Aktualisieren einer Rezension.
     */
    public function update(ReviewRequest $request, Review $review)
    {
        $user = Auth::user();
        $role = $this->getRoleInMemberTeam();

        if ($review->user_id === $user->id || in_array($role, [Role::Vorstand, Role::Admin], true)) {
            $data = $request->validated();

            $review->update($data);

            return redirect()
                ->route('reviews.show', $review->book)
                ->with('success', 'Rezension erfolgreich aktualisiert.');
        }

        abort(403);
    }

    /**
     * Löschen einer Rezension.
     */
    public function destroy(Review $review)
    {
        $user = Auth::user();
        $role = $this->getRoleInMemberTeam();

        if ($review->user_id === $user->id || in_array($role, [Role::Vorstand, Role::Admin], true)) {
            $review->delete();

            return back()->with('success', 'Rezension gelöscht.');
        }

        abort(403);
    }
}
