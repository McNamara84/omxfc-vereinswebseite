<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TourAssignmentSource;
use App\Enums\TourAssignmentStatus;
use App\Models\Team;
use App\Models\TourRewardGrant;
use App\Models\UserPoint;
use App\Services\Profile\ProfileTourCompletionService;
use App\Services\Profile\SeriesProfileCompletion;
use App\Services\TourAssignmentService;
use App\Services\TourRegistry;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(ProfileTourCompletionTestFixtures::class);
dataset('ProfileTourCompletionTest.missingFields', static function (): iterable {
    foreach (array_keys(SeriesProfileCompletion::FIELDS) as $field) {
        foreach ([null, '', " \t\n "] as $index => $value) {
            yield $field.'-'.$index => [$field, $value];
        }
    }
});
test('first completion books five points in members team and retry is stable', function (): void {
    $user = $this->member();
    $assignment = $this->assignment($user);
    $otherTeam = Team::factory()->create();
    $user->forceFill(['current_team_id' => $otherTeam->id])->save();
    $this->actingAs($user)->postJson(route('touren.complete', $assignment))->assertOk()->assertJsonPath('reward.points_awarded', 5)->assertJsonPath('reward.status', 'awarded');
    $completedAt = $assignment->fresh()->completed_at;
    $this->travel(2)->minutes();
    $user->forceFill(['lieblingsmutation' => null])->save();
    $this->postJson(route('touren.complete', $assignment))->assertOk()->assertJsonPath('reward.points_awarded', 0);
    expect($completedAt->equalTo($assignment->fresh()->completed_at))->toBeTrue();
    $this->assertDatabaseHas('user_points', ['user_id' => $user->id, 'team_id' => Team::membersTeam()->id, 'points' => 5]);
    expect(TourRewardGrant::where('user_id', $user->id)->count())->toBe(1)
        ->and(UserPoint::where('user_id', $user->id)->count())->toBe(1);
});
test('reward status reads grants without schema lookups', function (bool $rewardReceived): void {
    $user = $this->member();
    $assignment = $this->assignment($user);
    $service = app(TourAssignmentService::class);
    if ($rewardReceived) {
        $service->complete($assignment);
    }
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        $overview = $service->selfServiceOverviewForUser($user)->firstWhere('definition.key', 'profilpflege');
        $payload = $service->payloadForAssignment($assignment);
        expect($overview['reward_received'])->toBe($rewardReceived)
            ->and($payload['reward_received'])->toBe($rewardReceived);
        $schemaQueries = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql) => preg_match('/sqlite_(?:master|schema)|information_schema|pg_catalog|pragma/i', $sql));
        expect($schemaQueries)->toBeEmpty();
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
})->with([[false], [true]]);
test('missing reward grants migration is not reported as unrewarded', function (string $surface): void {
    $user = $this->member();
    $assignment = $this->assignment($user, false);
    Schema::drop('tour_reward_grants');
    $this->expectException(QueryException::class);
    $this->expectExceptionMessage('tour_reward_grants');
    $service = app(TourAssignmentService::class);
    if ($surface === 'overview') {
        $service->selfServiceOverviewForUser($user);
    } else {
        $service->payloadForAssignment($assignment);
    }
})->with([['overview'], ['payload']]);
test('completion results keep assignments persistable without response attributes', function (): void {
    $user = $this->member();
    $assignment = $this->assignment($user);
    $service = app(TourAssignmentService::class);
    foreach (['first', 'retry', 'restart'] as $attempt) {
        if ($attempt === 'restart') {
            $service->reassign($user, 'profilpflege', TourAssignmentSource::SelfService);
            $assignment = $this->assignment($user);
        }
        $result = $service->complete($assignment);
        expect($result->reward)->toBe(['status' => $attempt === 'first' ? 'awarded' : 'already_awarded', 'points_awarded' => $attempt === 'first' ? 5 : 0])
            ->and($result->assignment->is($assignment))->toBeTrue()
            ->and($result->assignment->status)->toBe(TourAssignmentStatus::Completed)
            ->and($result->assignment->getAttributes())->not->toHaveKey('reward_result');
        $result->assignment->forceFill(['metadata' => ['completion_attempt' => $attempt]])->save();
        expect($assignment->fresh()->metadata)->toBe(['completion_attempt' => $attempt]);
    }
    expect(UserPoint::where('user_id', $user->id)->count())->toBe(1)
        ->and(TourRewardGrant::where('user_id', $user->id)->count())->toBe(1);
});
test('every missing saved field prevents completion', function (string $field, ?string $value): void {
    $user = $this->member();
    $assignment = $this->assignment($user);
    $user->forceFill([$field => $value])->save();
    $this->actingAs($user)->postJson(route('touren.complete', $assignment), ['complete' => true, 'filled' => 11, $field => 'Nur im Browser', 'points' => 999])->assertUnprocessable()->assertJsonValidationErrors($field);
    expect($assignment->fresh()->status)->toBe(TourAssignmentStatus::InProgress);
    $this->assertDatabaseMissing('tour_reward_grants', ['user_id' => $user->id]);
    $this->assertDatabaseMissing('user_points', ['user_id' => $user->id]);
})->with('ProfileTourCompletionTest.missingFields');
test('incomplete tour can be resumed and rewarded later', function (): void {
    $user = $this->member(false);
    $assignment = $this->assignment($user);
    $this->actingAs($user)->postJson(route('touren.complete', $assignment))->assertUnprocessable();
    $this->postJson(route('touren.dismiss', $assignment))->assertOk();
    $this->post(route('touren.resume', $assignment))->assertRedirect(route('profile.show'));
    expect($assignment->fresh()->current_step_key)->toBe('profile-complete');
    $user->forceFill(array_fill_keys(array_keys(SeriesProfileCompletion::FIELDS), 'Favorit'))->save();
    $this->postJson(route('touren.complete', $assignment))->assertOk()->assertJsonPath('reward.points_awarded', 5);
});
test('unstarted tour or missing completion step cannot claim reward', function (): void {
    $user = $this->member();
    $assignment = $this->assignment($user, false);
    $this->actingAs($user)->postJson(route('touren.complete', $assignment))->assertUnprocessable();
    $this->postJson(route('touren.progress', $assignment), ['step_key' => 'profile-complete'])->assertUnprocessable();
    $this->postJson(route('touren.start', $assignment))->assertOk();
    $this->postJson(route('touren.complete', $assignment))->assertUnprocessable();
    $this->assertDatabaseMissing('user_points', ['user_id' => $user->id]);
});
test('previous completion does not exclude reward but stale request is rejected', function (): void {
    $user = $this->member();
    $old = $this->assignment($user);
    $old->forceFill(['tour_version' => 2, 'status' => TourAssignmentStatus::Completed, 'completed_at' => now()])->save();
    $this->actingAs($user)->postJson(route('touren.complete', $old))->assertStatus(409);
    $current = $this->assignment($user);
    $this->postJson(route('touren.complete', $current))->assertOk()->assertJsonPath('reward.points_awarded', 5);
});
test('restarts and later versions never pay again', function (): void {
    $user = $this->member();
    $this->actingAs($user)->postJson(route('touren.complete', $this->assignment($user)))->assertOk();
    foreach ([TourAssignmentSource::SelfService, TourAssignmentSource::Manual] as $source) {
        app(TourAssignmentService::class)->reassign($user, 'profilpflege', $source);
        $this->postJson(route('touren.complete', $this->assignment($user)))->assertOk()->assertJsonPath('reward.points_awarded', 0);
    }
    config(['tours.profilpflege.version' => 4]);
    app()->forgetInstance(TourRegistry::class);
    app()->forgetInstance(TourAssignmentService::class);
    $this->postJson(route('touren.complete', $this->assignment($user)))->assertOk()->assertJsonPath('reward.points_awarded', 0);
    expect((int) UserPoint::where('user_id', $user->id)->sum('points'))->toBe(5);
});
test('foreign or no longer active member cannot claim', function (): void {
    $owner = $this->member();
    $assignment = $this->assignment($owner);
    $this->actingAs($this->member())->postJson(route('touren.complete', $assignment))->assertForbidden();
    Team::membersTeam()->users()->updateExistingPivot($owner->id, ['role' => Role::Anwaerter->value]);
    // The existing member middleware redirects applicants before the controller.
    $this->actingAs($owner)->postJson(route('touren.complete', $assignment))->assertRedirect();
    Team::membersTeam()->users()->detach($owner);
    try {
        app(ProfileTourCompletionService::class)->complete($assignment);
        $this->fail('Removed membership must be rejected.');
    } catch (HttpException $error) {
        expect($error->getStatusCode())->toBe(403);
    }
    $this->assertDatabaseMissing('user_points', ['user_id' => $owner->id]);
});
test('grant failure rolls back booking and completion and allows retry', function (): void {
    $user = $this->member();
    $assignment = $this->assignment($user);
    $fail = true;
    TourRewardGrant::creating(function () use (&$fail) {
        if ($fail) {
            throw new RuntimeException('Simulated grant failure');
        }
    });
    try {
        app(TourAssignmentService::class)->complete($assignment);
        $this->fail('Grant failure should abort completion.');
    } catch (RuntimeException $error) {
        expect($error->getMessage())->toBe('Simulated grant failure');
    } finally {
        $fail = false;
    }
    $this->assertDatabaseMissing('user_points', ['user_id' => $user->id]);
    expect($assignment->fresh()->status)->toBe(TourAssignmentStatus::InProgress);
    $this->actingAs($user)->postJson(route('touren.complete', $assignment))->assertOk()->assertJsonPath('reward.points_awarded', 5);
});
test('deleted booking or assignment does not remove once only proof', function (): void {
    $user = $this->member();
    $assignment = $this->assignment($user);
    app(TourAssignmentService::class)->complete($assignment);
    UserPoint::where('user_id', $user->id)->firstOrFail()->delete();
    $assignment->delete();
    $grant = TourRewardGrant::where('user_id', $user->id)->firstOrFail();
    expect($grant->user_point_id)->toBeNull()
        ->and($grant->tour_assignment_id)->toBeNull();
    $this->actingAs($user)->postJson(route('touren.complete', $this->assignment($user)))->assertOk()->assertJsonPath('reward.points_awarded', 0);
    $migration = require database_path('migrations/2026_10_25_120000_create_tour_reward_grants_table.php');
    expect(fn () => $migration->down())->toThrow(RuntimeException::class);
});
test('invitation does not start tour and resume removes suppression', function (): void {
    $user = $this->member();
    $assignment = $this->assignment($user, false);
    $this->actingAs($user)->getJson(route('touren.current'))->assertOk()->assertJsonPath('tour.start_mode', 'invitation')->assertJsonPath('tour.active_in_session', false)->assertJsonPath('tour.profile.filled', 11)->assertJsonPath('tour.reward_received', false)->assertJsonPath('tour.reward_points', ProfileTourCompletionService::POINTS);
    expect($assignment->fresh()->started_at)->toBeNull();
    $this->postJson(route('touren.dismiss', $assignment))->assertOk();
    $this->getJson(route('touren.current'))->assertJsonPath('tour', null);
    $this->post(route('touren.resume', $assignment))->assertRedirect(route('profile.show'));
    $this->getJson(route('touren.current'))->assertJsonPath('tour.active_in_session', true);
});
test('old open assignment does not reset current completed assignment', function (): void {
    $user = $this->member();
    $current = $this->assignment($user);
    app(TourAssignmentService::class)->complete($current);
    $old = $current->replicate();
    $old->forceFill(['tour_version' => 2, 'status' => TourAssignmentStatus::Pending, 'completed_at' => null])->save();
    $resolved = app(TourAssignmentService::class)->activeAssignment($old);
    expect($resolved->id)->toBe($current->id)
        ->and($resolved->status)->toBe(TourAssignmentStatus::Completed)
        ->and(app(TourAssignmentService::class)->currentPromptableAssignmentForUser($user))->toBeNull()
        ->and(DB::table('tour_reward_grants')->where('user_id', $user->id)->count())->toBe(1);
});
