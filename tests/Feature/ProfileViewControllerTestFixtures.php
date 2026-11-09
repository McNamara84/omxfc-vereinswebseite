<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Models\Team;
use App\Models\Todo;
use App\Models\TodoCategory;
use App\Models\User;
use App\Models\UserPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait ProfileViewControllerTestFixtures
{
    use RefreshDatabase;

    private function createMember(string $role = 'Mitglied', array $attributes = []): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(array_merge(['current_team_id' => $team->id], $attributes));
        $team->users()->attach($user, ['role' => Role::from($role)->value]);

        return $user;
    }

    private function createTodoWithPoints(User $user, TodoCategory $category, int $points): void
    {
        $team = Team::membersTeam();
        $todo = Todo::create(['team_id' => $team->id, 'created_by' => $user->id, 'title' => 'Task', 'points' => $points, 'category_id' => $category->id]);
        UserPoint::create(['user_id' => $user->id, 'team_id' => $team->id, 'todo_id' => $todo->id, 'points' => $points]);
    }

    private function createCompletedSwaps(User $member, User $partner, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $offer = BookOffer::create(['user_id' => $member->id, 'series' => 'Series', 'book_number' => $i + 1, 'book_title' => 'Title', 'condition' => 'neu']);
            $request = BookRequest::create(['user_id' => $partner->id, 'series' => 'Series', 'book_number' => $i + 1, 'book_title' => 'Title', 'condition' => 'neu']);
            BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id, 'completed_at' => now()]);
        }
    }
}
