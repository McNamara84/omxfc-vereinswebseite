<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\Team;
use App\Models\User;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RezensionFilterTestFixtures::class);

test('filter by roman number', function (): void {
    $this->putBookData();
    Book::create(['roman_number' => 1, 'title' => 'Alpha', 'author' => 'A']);
    Book::create(['roman_number' => 2, 'title' => 'Beta', 'author' => 'B']);
    $user = $this->actingMember();
    $this->actingAs($user);
    $this->get('/rezensionen?roman_number=1')->assertOk()->assertSee('Alpha')->assertDontSee('Beta');
});

test('filter by review status', function (): void {
    $this->putBookData();
    $book1 = Book::create(['roman_number' => 1, 'title' => 'Alpha', 'author' => 'A']);
    $book2 = Book::create(['roman_number' => 2, 'title' => 'Beta', 'author' => 'B']);
    Review::create(['team_id' => Team::membersTeam()->id, 'user_id' => User::factory()->create()->id, 'book_id' => $book1->id, 'title' => 'R', 'content' => str_repeat('A', 140)]);
    $user = $this->actingMember();
    $this->actingAs($user);
    $this->get('/rezensionen?review_status=without')->assertOk()->assertSee('Beta')->assertDontSee('Alpha');
});

test('filter by author', function (): void {
    $this->putBookData();
    Book::create(['roman_number' => 1, 'title' => 'Alpha', 'author' => 'A']);
    Book::create(['roman_number' => 2, 'title' => 'Beta', 'author' => 'B']);
    $user = $this->actingMember();
    $this->actingAs($user);
    $this->get('/rezensionen?author=B')->assertOk()->assertSee('Beta')->assertDontSee('Alpha');
});
