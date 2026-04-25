<?php

namespace App\Livewire;

use App\Enums\Role;
use App\Models\Book;
use App\Models\Review;
use App\Services\MembersTeamProvider;
use App\Services\UserRoleService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RezensionShow extends Component
{
    #[Locked]
    public int $bookId;

    public ?int $confirmingDeleteReview = null;

    public function mount(Book $book): void
    {
        $this->bookId = $book->id;

        $user = Auth::user();
        $role = $this->userRole;
        $teamId = app(MembersTeamProvider::class)->getMembersTeamOrAbort()->id;

        $hasOwn = $book->reviews()
            ->where('team_id', $teamId)
            ->where('user_id', $user->id)
            ->exists();

        if (! $hasOwn && ! in_array($role, [Role::Ehrenmitglied, Role::Vorstand, Role::Admin], true)) {
            $this->redirect(route('reviews.create', $book));
        }
    }

    #[Computed]
    public function userRole(): ?Role
    {
        try {
            return app(UserRoleService::class)->getRole(
                Auth::user(),
                app(MembersTeamProvider::class)->getMembersTeamOrAbort()
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            abort(403);
        }
    }

    #[Computed]
    public function book(): Book
    {
        return Book::findOrFail($this->bookId);
    }

    #[Computed]
    public function reviews()
    {
        return $this->book->reviews()
            ->with(['user', 'comments' => function ($query) {
                $query->with(['user', 'children.user'])->orderBy('created_at');
            }])
            ->get();
    }

    public function deleteReview(int $reviewId): void
    {
        $review = Review::findOrFail($reviewId);
        $user = Auth::user();
        $role = $this->userRole;

        if ($review->user_id !== $user->id && ! in_array($role, [Role::Vorstand, Role::Admin], true)) {
            abort(403);
        }

        $review->delete();
        $this->confirmingDeleteReview = null;
        unset($this->reviews);
        $this->dispatch('toast', type: 'success', title: 'Rezension gelöscht.');
    }

    public function render()
    {
        $book = $this->book;

        return view('livewire.rezension-show', [
            'role' => $this->userRole,
        ])->layout('layouts.app', [
            'title' => 'Rezensionen zu '.$book->title.' – Offizieller MADDRAX Fanclub e. V.',
            'description' => 'Leserrezensionen zum Roman "'.$book->title.'".',
        ]);
    }

    public function placeholder()
    {
        return view('components.skeleton-detail', ['hasImage' => true, 'sections' => 4]);
    }
}
