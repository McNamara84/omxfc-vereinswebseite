<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Book;
use App\Models\Review;
use App\Models\Team;

class RezensionFilterTest extends TestCase
{
    use RefreshDatabase;

    private function actingMember(): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => 'Mitglied']);
        return $user;
    }

    private function putBookData(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('maddrax.json', json_encode([
            ['nummer' => 1, 'titel' => 'Roman1', 'zyklus' => 'Z1'],
            ['nummer' => 2, 'titel' => 'Roman2', 'zyklus' => 'Z1'],
        ]));
    }

    public function test_filter_by_roman_number(): void
    {
        $this->putBookData();
        Book::create(['roman_number' => 1, 'title' => 'Alpha', 'author' => 'A']);
        Book::create(['roman_number' => 2, 'title' => 'Beta', 'author' => 'B']);

        $user = $this->actingMember();
        $this->actingAs($user);

        $this->get('/rezensionen?roman_number=1')
            ->assertOk()
            ->assertSee('Alpha')
            ->assertDontSee('Beta');
    }

    public function test_filter_by_review_status(): void
    {
        $this->putBookData();
        $book1 = Book::create(['roman_number' => 1, 'title' => 'Alpha', 'author' => 'A']);
        $book2 = Book::create(['roman_number' => 2, 'title' => 'Beta', 'author' => 'B']);
        Review::create([
            'team_id' => Team::membersTeam()->id,
            'user_id' => User::factory()->create()->id,
            'book_id' => $book1->id,
            'title' => 'R',
            'content' => str_repeat('A', 140),
        ]);

        $user = $this->actingMember();
        $this->actingAs($user);

        $this->get('/rezensionen?review_status=without')
            ->assertOk()
            ->assertSee('Beta')
            ->assertDontSee('Alpha');
    }

    public function test_filter_by_author(): void
    {
        $this->putBookData();
        Book::create(['roman_number' => 1, 'title' => 'Alpha', 'author' => 'A']);
        Book::create(['roman_number' => 2, 'title' => 'Beta', 'author' => 'B']);

        $user = $this->actingMember();
        $this->actingAs($user);

        $this->get('/rezensionen?author=B')
            ->assertOk()
            ->assertSee('Beta')
            ->assertDontSee('Alpha');
    }
}
