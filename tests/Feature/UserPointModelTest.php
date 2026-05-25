<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Activity;
use App\Models\BaxxEarningProgress;
use App\Models\Team;
use App\Models\Todo;
use App\Models\TodoCategory;
use App\Models\User;
use App\Models\UserPoint;
use App\Services\BaxxMilestoneActivityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPointModelTest extends TestCase
{
    use RefreshDatabase;

    private function createMember(): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::Mitglied->value]);

        return $user;
    }

    private function createTodo(User $creator): Todo
    {
        $team = Team::membersTeam();
        $category = TodoCategory::first() ?? TodoCategory::create(['name' => 'Test', 'slug' => 'test']);

        return Todo::create([
            'team_id' => $team->id,
            'created_by' => $creator->id,
            'title' => 'Todo',
            'points' => 5,
            'category_id' => $category->id,
        ]);
    }

    public function test_user_point_can_be_created(): void
    {
        $user = $this->createMember();
        $todo = $this->createTodo($user);

        $userPoint = UserPoint::create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'todo_id' => $todo->id,
            'points' => 10,
        ]);

        $this->assertDatabaseHas('user_points', [
            'id' => $userPoint->id,
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'todo_id' => $todo->id,
            'points' => 10,
        ]);
    }

    public function test_user_point_relations_return_models(): void
    {
        $user = $this->createMember();
        $todo = $this->createTodo($user);

        $userPoint = UserPoint::create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'todo_id' => $todo->id,
            'points' => 1,
        ]);

        $this->assertTrue($userPoint->user->is($user));
        $this->assertTrue($userPoint->team->is($user->currentTeam));
        $this->assertTrue($userPoint->todo->is($todo));
    }

    public function test_total_points_for_team_returns_sum(): void
    {
        $user = $this->createMember();
        $todo = $this->createTodo($user);

        UserPoint::create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'todo_id' => $todo->id,
            'points' => 2,
        ]);
        // Zweiter Eintrag ohne Todo-Bezug, da user_points.todo_id seit der
        // Unique-Constraint pro Todo nur eine Gutschrift erlaubt; Bonuspunkte
        // ohne Todo werden im Code via incrementTeamPoints() vergeben.
        UserPoint::create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'todo_id' => null,
            'points' => 3,
        ]);

        $points = $user->totalPointsForTeam($user->currentTeam);

        $this->assertSame(5, $points);
    }

    public function test_increment_team_points_creates_entry(): void
    {
        $user = $this->createMember();

        $user->incrementTeamPoints(4);

        $this->assertDatabaseHas('user_points', [
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'points' => 4,
        ]);
    }

    public function test_first_user_point_creates_initial_baxx_milestone_activity(): void
    {
        $user = $this->createMember();

        UserPoint::create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'todo_id' => null,
            'points' => 1,
        ]);

        $this->assertDatabaseHas('activities', [
            'user_id' => $user->id,
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'action' => 'baxx_milestone_reached_1',
        ]);
    }

    public function test_large_point_jump_creates_each_crossed_baxx_milestone_once(): void
    {
        $user = $this->createMember();

        UserPoint::create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'todo_id' => null,
            'points' => 130,
        ]);

        $actions = Activity::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->pluck('action')
            ->all();

        $this->assertContains('baxx_milestone_reached_1', $actions);
        $this->assertContains('baxx_milestone_reached_25', $actions);
        $this->assertContains('baxx_milestone_reached_100', $actions);
        $this->assertNotContains('baxx_milestone_reached_250', $actions);
    }

    public function test_follow_up_points_do_not_duplicate_previous_baxx_milestones(): void
    {
        $user = $this->createMember();

        UserPoint::create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'todo_id' => null,
            'points' => 80,
        ]);

        UserPoint::create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'todo_id' => null,
            'points' => 40,
        ]);

        $actions = Activity::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->pluck('action')
            ->all();

        $this->assertSame(1, collect($actions)->filter(fn (string $action) => $action === 'baxx_milestone_reached_1')->count());
        $this->assertSame(1, collect($actions)->filter(fn (string $action) => $action === 'baxx_milestone_reached_25')->count());
        $this->assertSame(1, collect($actions)->filter(fn (string $action) => $action === 'baxx_milestone_reached_100')->count());
    }

    public function test_reprocessing_same_user_point_is_idempotent_and_keeps_progress_timestamp(): void
    {
        $user = $this->createMember();

        Carbon::setTestNow('2026-05-25 12:00:00');

        try {
            $userPoint = UserPoint::create([
                'user_id' => $user->id,
                'team_id' => $user->currentTeam->id,
                'todo_id' => null,
                'points' => 25,
            ]);

            $progress = BaxxEarningProgress::query()
                ->where('user_id', $user->id)
                ->where('action_key', 'dashboard_baxx_milestone')
                ->firstOrFail();

            $this->assertSame(25, $progress->processed_count);

            $initialUpdatedAt = $progress->updated_at?->copy();

            Carbon::setTestNow('2026-05-25 12:05:00');

            app(BaxxMilestoneActivityService::class)->recordForUserPoint($userPoint->id);

            $progress->refresh();

            $actions = Activity::query()
                ->where('subject_type', User::class)
                ->where('subject_id', $user->id)
                ->pluck('action')
                ->all();

            $this->assertSame($initialUpdatedAt?->toDateTimeString(), $progress->updated_at?->toDateTimeString());
            $this->assertSame(1, collect($actions)->filter(fn (string $action) => $action === 'baxx_milestone_reached_1')->count());
            $this->assertSame(1, collect($actions)->filter(fn (string $action) => $action === 'baxx_milestone_reached_25')->count());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_points_for_other_team_do_not_create_members_baxx_milestone_activity(): void
    {
        $user = $this->createMember();
        $otherTeam = Team::factory()->create(['personal_team' => false, 'name' => 'Nebenverein']);
        $otherTeam->users()->attach($user, ['role' => Role::Mitglied->value]);

        UserPoint::create([
            'user_id' => $user->id,
            'team_id' => $otherTeam->id,
            'todo_id' => null,
            'points' => 100,
        ]);

        $this->assertDatabaseMissing('activities', [
            'user_id' => $user->id,
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'action' => 'baxx_milestone_reached_1',
        ]);
    }

    public function test_existing_history_does_not_create_retroactive_baxx_milestones_when_feature_starts_late(): void
    {
        $user = $this->createMember();

        UserPoint::create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'todo_id' => null,
            'points' => 90,
        ]);

        Activity::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->delete();

        BaxxEarningProgress::query()
            ->where('user_id', $user->id)
            ->where('action_key', 'dashboard_baxx_milestone')
            ->delete();

        UserPoint::create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'todo_id' => null,
            'points' => 15,
        ]);

        $actions = Activity::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->pluck('action')
            ->all();

        $this->assertSame(['baxx_milestone_reached_100'], $actions);
    }
}
