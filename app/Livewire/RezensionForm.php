<?php

namespace App\Livewire;

use App\Enums\Role;
use App\Mail\NewReviewNotification;
use App\Models\Activity;
use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use App\Services\MembersTeamProvider;
use App\Services\ReviewBaxxService;
use App\Services\UserRoleService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RezensionForm extends Component
{
    #[Locked]
    public int $bookId;

    #[Locked]
    public ?int $reviewId = null;

    public string $title = '';

    public string $content = '';

    public function mount(?Book $book = null, ?Review $review = null): void
    {
        $user = Auth::user();

        try {
            $membersTeam = app(MembersTeamProvider::class)->getMembersTeamOrAbort();
            $role = app(UserRoleService::class)->getRole($user, $membersTeam);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            abort(403);
        }

        $teamId = $membersTeam->id;

        if ($review?->exists) {
            // Edit mode
            $this->bookId = $review->book_id;

            if ($review->user_id !== $user->id && ! in_array($role, [Role::Vorstand, Role::Admin], true)) {
                abort(403);
            }

            $this->reviewId = $review->id;
            $this->title = $review->title;
            $this->content = $review->content;
        } elseif ($book?->exists) {
            // Create mode
            $this->bookId = $book->id;

            $hasOwn = $book->reviews()
                ->where('team_id', $teamId)
                ->where('user_id', $user->id)
                ->exists();

            if ($hasOwn) {
                $this->redirect(route('reviews.show', $book));

                return;
            }

            if (! in_array($role, [Role::Mitglied, Role::Vorstand, Role::Kassenwart, Role::Admin], true)) {
                abort(403);
            }
        } else {
            abort(404);
        }
    }

    public function save(): void
    {
        // Strip heading markers before validation (same as ReviewRequest)
        $this->content = preg_replace('/^\s*#+\s*/m', '', $this->content);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'min:140'],
        ]);

        $user = Auth::user();
        $teamId = app(MembersTeamProvider::class)->getMembersTeamOrAbort()->id;
        $book = Book::findOrFail($this->bookId);

        if ($this->reviewId) {
            $review = Review::findOrFail($this->reviewId);
            $role = app(UserRoleService::class)->getRole($user, app(MembersTeamProvider::class)->getMembersTeamOrAbort());

            if ($review->user_id !== $user->id && ! in_array($role, [Role::Vorstand, Role::Admin], true)) {
                abort(403);
            }

            $review->update($validated);

            session()->flash('toast', ['type' => 'success', 'title' => 'Rezension erfolgreich aktualisiert.']);
            $this->redirect(route('reviews.show', $book), navigate: true);
        } else {
            $hasOwn = $book->reviews()
                ->where('team_id', $teamId)
                ->where('user_id', $user->id)
                ->exists();
            $role = app(UserRoleService::class)->getRole($user, app(MembersTeamProvider::class)->getMembersTeamOrAbort());

            if ($hasOwn || ! in_array($role, [Role::Mitglied, Role::Vorstand, Role::Kassenwart, Role::Admin], true)) {
                abort(403);
            }

            $review = Review::create([
                'team_id' => $teamId,
                'user_id' => $user->id,
                'book_id' => $book->id,
                'title' => $validated['title'],
                'content' => $validated['content'],
            ]);

            $reviewCount = Review::where('team_id', $teamId)
                ->where('user_id', $user->id)
                ->count();
            app(ReviewBaxxService::class)->awardPointsForReview($user, $reviewCount);

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

            session()->flash('toast', ['type' => 'success', 'title' => 'Rezension erfolgreich erstellt.']);
            $this->redirect(route('reviews.show', $book), navigate: true);
        }
    }

    public function render()
    {
        $book = Book::findOrFail($this->bookId);
        $isEdit = (bool) $this->reviewId;
        $formTitle = $isEdit
            ? 'Rezension zu „'.$book->title.'" bearbeiten'
            : 'Neue Rezension zu „'.$book->title.'" (Nr. '.$book->roman_number.')';

        return view('livewire.rezension-form', [
            'book' => $book,
            'isEdit' => $isEdit,
            'formTitle' => $formTitle,
        ])->layout('layouts.app', [
            'title' => ($isEdit ? 'Rezension bearbeiten' : 'Rezension verfassen').' – Offizieller MADDRAX Fanclub e. V.',
            'description' => ($isEdit ? 'Überarbeite' : 'Schreibe').' deine Rezension zum Roman "'.$book->title.'".',
        ]);
    }

    public function placeholder()
    {
        return view('components.skeleton-form', ['fields' => 4, 'hasTextarea' => true]);
    }
}
