<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait ReviewCommentModelTestFixtures
{
    use RefreshDatabase;

    private function createMember(string $role = 'Mitglied'): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role]);

        return $user;
    }

    private function createReview(?User $author = null): Review
    {
        $team = Team::membersTeam();
        $author = $author ?: $this->createMember();
        $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Foo']);

        return Review::create(['team_id' => $team->id, 'user_id' => $author->id, 'book_id' => $book->id, 'title' => 'Review', 'content' => str_repeat('B', 140)]);
    }
}
