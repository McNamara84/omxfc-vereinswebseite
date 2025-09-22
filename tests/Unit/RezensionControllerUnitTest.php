<?php

namespace Tests\Unit;

use App\Enums\Role;
use App\Http\Controllers\RezensionController;
use App\Models\Book;
use App\Models\Review;
use App\Models\Team;
use App\Models\User;
use App\Services\UserRoleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RezensionControllerUnitTest extends TestCase
{
    use RefreshDatabase;

    private function makeController(): RezensionControllerProxy
    {
        $service = $this->app->make(UserRoleService::class);

        return new RezensionControllerProxy($service);
    }

    private function attachMember(User $user, Role $role = Role::Mitglied): Team
    {
        $team = Team::membersTeam();

        if (! $team) {
            $owner = User::factory()->create();
            $team = Team::factory()->create([
                'name' => 'Mitglieder',
                'user_id' => $owner->id,
                'personal_team' => false,
            ]);
            $team->users()->attach($owner, ['role' => Role::Admin->value]);
            $owner->forceFill(['current_team_id' => $team->id])->save();
        }

        $team->users()->attach($user, ['role' => $role->value]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $team;
    }

    public function test_prepare_book_query_adds_review_metadata(): void
    {
        $controller = $this->makeController();

        $user = User::factory()->create();
        $other = User::factory()->create();
        $team = $this->attachMember($user);
        $this->attachMember($other);

        $bookWithReview = Book::create([
            'roman_number' => 2,
            'title' => 'Mit Rezension',
            'author' => 'Autorin',
        ]);

        $bookWithoutReview = Book::create([
            'roman_number' => 3,
            'title' => 'Ohne Rezension',
            'author' => 'Schreiber',
        ]);

        Review::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'book_id' => $bookWithReview->id,
            'title' => 'Meine Meinung',
            'content' => str_repeat('A', 140),
        ]);

        Review::create([
            'team_id' => $team->id,
            'user_id' => $other->id,
            'book_id' => $bookWithReview->id,
            'title' => 'Zweite Meinung',
            'content' => str_repeat('B', 140),
        ]);

        $query = Book::query()->whereIn('id', [$bookWithReview->id, $bookWithoutReview->id]);

        $results = $controller->callPrepareBookQuery($query, $user, $team->id);

        $this->assertSame([
            $bookWithReview->id,
            $bookWithoutReview->id,
        ], $results->pluck('id')->all());

        $withReview = $results->firstWhere('id', $bookWithReview->id);
        $withoutReview = $results->firstWhere('id', $bookWithoutReview->id);

        $this->assertSame(2, $withReview->reviews_count);
        $this->assertTrue($withReview->has_review);
        $this->assertSame(0, $withoutReview->reviews_count);
        $this->assertFalse($withoutReview->has_review);
    }

    public function test_prepare_book_query_respects_sort_direction(): void
    {
        $controller = $this->makeController();

        $user = User::factory()->create();
        $team = $this->attachMember($user);

        $earlyBook = Book::create([
            'roman_number' => 4,
            'title' => 'Früher Roman',
            'author' => 'Autor',
        ]);

        $lateBook = Book::create([
            'roman_number' => 7,
            'title' => 'Später Roman',
            'author' => 'Autorin',
        ]);

        $query = Book::query()->whereIn('id', [$earlyBook->id, $lateBook->id]);

        $descending = $controller->callPrepareBookQuery($query, $user, $team->id, 'desc');

        $this->assertSame([
            $lateBook->id,
            $earlyBook->id,
        ], $descending->pluck('id')->all());

        $ascending = $controller->callPrepareBookQuery($query, $user, $team->id, 'asc');

        $this->assertSame([
            $earlyBook->id,
            $lateBook->id,
        ], $ascending->pluck('id')->all());
    }
}

class RezensionControllerProxy extends RezensionController
{
    public function callPrepareBookQuery(Builder $query, User $user, int $teamId, string $direction = 'asc')
    {
        return $this->prepareBookQuery($query, $user, $teamId, $direction);
    }
}
