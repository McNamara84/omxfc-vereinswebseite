<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Activity;
use App\Models\BaxxEarningProgress;
use App\Models\Team;
use App\Models\Todo;
use App\Models\User;
use App\Models\UserPoint;
use App\Services\BaxxMilestoneActivityService;
use Carbon\Carbon;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(UserPointModelTestFixtures::class);

test('milestone callback tolerates a user deleted before locking', function (): void {
    $member = $this->createMember();
    $point = UserPoint::withoutEvents(fn () => UserPoint::create(['user_id' => $member->id, 'team_id' => $member->current_team_id, 'points' => 5]));
    // Keep existing model observers, but isolate this test's destructive listener.
    $dispatcher = UserPoint::getEventDispatcher();
    UserPoint::setEventDispatcher(clone $dispatcher);
    try {
        // Simulate account removal between loading the booking and taking the lock.
        UserPoint::retrieved(function (UserPoint $loaded) use ($point, $member): void {
            if ($loaded->id === $point->id) {
                User::whereKey($member->id)->delete();
            }
        });
        $service = app(BaxxMilestoneActivityService::class);
        $service->recordForUserPoint($point->id);
        $service->recordForUserPoint($point->id);
    } finally {
        UserPoint::setEventDispatcher($dispatcher);
    }
    $this->assertDatabaseMissing('user_points', ['id' => $point->id]);
    $this->assertDatabaseMissing('baxx_earning_progress', ['user_id' => $member->id]);
    $this->assertDatabaseMissing('activities', ['user_id' => $member->id, 'action' => 'baxx_milestone_reached_1']);
    // A later test can reuse both IDs after its transaction is rolled back.
    $replacement = User::factory()->create(['id' => $member->id, 'current_team_id' => $member->current_team_id]);
    UserPoint::withoutEvents(fn () => UserPoint::forceCreate(['id' => $point->id, 'user_id' => $replacement->id, 'team_id' => $member->current_team_id, 'points' => 5]));
    UserPoint::findOrFail($point->id);
    $this->assertDatabaseHas('users', ['id' => $replacement->id]);
});

test('user point can be created', function (): void {
    $user = $this->createMember();
    $todo = $this->createTodo($user);
    $userPoint = UserPoint::create(['user_id' => $user->id, 'team_id' => $user->currentTeam->id, 'todo_id' => $todo->id, 'points' => 10]);
    $this->assertDatabaseHas('user_points', ['id' => $userPoint->id, 'user_id' => $user->id, 'team_id' => $user->currentTeam->id, 'todo_id' => $todo->id, 'points' => 10]);
});

test('user point relations return models', function (): void {
    $user = $this->createMember();
    $todo = $this->createTodo($user);
    $userPoint = UserPoint::create(['user_id' => $user->id, 'team_id' => $user->currentTeam->id, 'todo_id' => $todo->id, 'points' => 1]);
    expect($userPoint->user->is($user))->toBeTrue()
        ->and($userPoint->team->is($user->currentTeam))->toBeTrue()
        ->and($userPoint->todo->is($todo))->toBeTrue();
});

test('total points for team returns sum', function (): void {
    $user = $this->createMember();
    $todo = $this->createTodo($user);
    UserPoint::create(['user_id' => $user->id, 'team_id' => $user->currentTeam->id, 'todo_id' => $todo->id, 'points' => 2]);
    // Zweiter Eintrag ohne Todo-Bezug, da user_points.todo_id seit der
    // Unique-Constraint pro Todo nur eine Gutschrift erlaubt; Bonuspunkte
    // ohne Todo werden im Code via incrementTeamPoints() vergeben.
    UserPoint::create(['user_id' => $user->id, 'team_id' => $user->currentTeam->id, 'todo_id' => null, 'points' => 3]);
    $points = $user->totalPointsForTeam($user->currentTeam);
    expect($points)->toBe(5);
});

test('increment team points creates entry', function (): void {
    $user = $this->createMember();
    $user->incrementTeamPoints(4);
    $this->assertDatabaseHas('user_points', ['user_id' => $user->id, 'team_id' => $user->currentTeam->id, 'points' => 4]);
});

test('first user point creates initial baxx milestone activity', function (): void {
    $user = $this->createMember();
    UserPoint::create(['user_id' => $user->id, 'team_id' => $user->currentTeam->id, 'todo_id' => null, 'points' => 1]);
    $this->assertDatabaseHas('activities', ['user_id' => $user->id, 'subject_type' => User::class, 'subject_id' => $user->id, 'action' => 'baxx_milestone_reached_1']);
});

test('large point jump creates each crossed baxx milestone once', function (): void {
    $user = $this->createMember();
    UserPoint::create(['user_id' => $user->id, 'team_id' => $user->currentTeam->id, 'todo_id' => null, 'points' => 130]);
    $actions = Activity::query()->where('subject_type', User::class)->where('subject_id', $user->id)->pluck('action')->all();
    expect($actions)->toContain('baxx_milestone_reached_1')
        ->toContain('baxx_milestone_reached_25')
        ->toContain('baxx_milestone_reached_100')->not->toContain('baxx_milestone_reached_250');
});

test('follow up points do not duplicate previous baxx milestones', function (): void {
    $user = $this->createMember();
    UserPoint::create(['user_id' => $user->id, 'team_id' => $user->currentTeam->id, 'todo_id' => null, 'points' => 80]);
    UserPoint::create(['user_id' => $user->id, 'team_id' => $user->currentTeam->id, 'todo_id' => null, 'points' => 40]);
    $actions = Activity::query()->where('subject_type', User::class)->where('subject_id', $user->id)->pluck('action')->all();
    expect(collect($actions)->filter(fn (string $action) => $action === 'baxx_milestone_reached_1')->count())->toBe(1)
        ->and(collect($actions)->filter(fn (string $action) => $action === 'baxx_milestone_reached_25')->count())->toBe(1)
        ->and(collect($actions)->filter(fn (string $action) => $action === 'baxx_milestone_reached_100')->count())->toBe(1);
});

test('reprocessing same user point is idempotent and keeps progress timestamp', function (): void {
    $user = $this->createMember();
    Carbon::setTestNow('2026-05-25 12:00:00');
    try {
        $userPoint = UserPoint::create(['user_id' => $user->id, 'team_id' => $user->currentTeam->id, 'todo_id' => null, 'points' => 25]);
        $progress = BaxxEarningProgress::query()->where('user_id', $user->id)->where('action_key', 'dashboard_baxx_milestone')->firstOrFail();
        expect($progress->processed_count)->toBe(25);
        $initialUpdatedAt = $progress->updated_at?->copy();
        Carbon::setTestNow('2026-05-25 12:05:00');
        app(BaxxMilestoneActivityService::class)->recordForUserPoint($userPoint->id);
        $progress->refresh();
        $actions = Activity::query()->where('subject_type', User::class)->where('subject_id', $user->id)->pluck('action')->all();
        expect($progress->updated_at?->toDateTimeString())->toBe($initialUpdatedAt?->toDateTimeString())
            ->and(collect($actions)->filter(fn (string $action) => $action === 'baxx_milestone_reached_1')->count())->toBe(1)
            ->and(collect($actions)->filter(fn (string $action) => $action === 'baxx_milestone_reached_25')->count())->toBe(1);
    } finally {
        Carbon::setTestNow();
    }
});

test('out of order initial processing still creates lowest milestones', function (): void {
    $user = $this->createMember();
    [$firstPoint, $secondPoint] = UserPoint::withoutEvents(function () use ($user): array {
        $firstPoint = UserPoint::create(['user_id' => $user->id, 'team_id' => $user->currentTeam->id, 'todo_id' => null, 'points' => 1]);
        $secondPoint = UserPoint::create(['user_id' => $user->id, 'team_id' => $user->currentTeam->id, 'todo_id' => null, 'points' => 24]);

        return [$firstPoint, $secondPoint];
    });
    $service = app(BaxxMilestoneActivityService::class);
    $service->recordForUserPoint($secondPoint->id);
    $service->recordForUserPoint($firstPoint->id);
    $actions = Activity::query()->where('subject_type', User::class)->where('subject_id', $user->id)->pluck('action')->all();
    $progress = BaxxEarningProgress::query()->where('user_id', $user->id)->where('action_key', 'dashboard_baxx_milestone')->firstOrFail();
    expect($progress->processed_count)->toBe(25)
        ->and(collect($actions)->filter(fn (string $action) => $action === 'baxx_milestone_reached_1')->count())->toBe(1)
        ->and(collect($actions)->filter(fn (string $action) => $action === 'baxx_milestone_reached_25')->count())->toBe(1);
});

test('points for other team do not create members baxx milestone activity', function (): void {
    $user = $this->createMember();
    $otherTeam = Team::factory()->create(['personal_team' => false, 'name' => 'Nebenverein']);
    $otherTeam->users()->attach($user, ['role' => Role::Mitglied->value]);
    UserPoint::create(['user_id' => $user->id, 'team_id' => $otherTeam->id, 'todo_id' => null, 'points' => 100]);
    $this->assertDatabaseMissing('activities', ['user_id' => $user->id, 'subject_type' => User::class, 'subject_id' => $user->id, 'action' => 'baxx_milestone_reached_1']);
});

test('existing history does not create retroactive baxx milestones when feature starts late', function (): void {
    $user = $this->createMember();
    UserPoint::create(['user_id' => $user->id, 'team_id' => $user->currentTeam->id, 'todo_id' => null, 'points' => 90]);
    Activity::query()->where('subject_type', User::class)->where('subject_id', $user->id)->delete();
    BaxxEarningProgress::query()->where('user_id', $user->id)->where('action_key', 'dashboard_baxx_milestone')->delete();
    UserPoint::create(['user_id' => $user->id, 'team_id' => $user->currentTeam->id, 'todo_id' => null, 'points' => 15]);
    $actions = Activity::query()->where('subject_type', User::class)->where('subject_id', $user->id)->pluck('action')->all();
    expect($actions)->toBe(['baxx_milestone_reached_100']);
});
