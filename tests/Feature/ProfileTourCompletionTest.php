<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TourAssignmentSource;
use App\Enums\TourAssignmentStatus;
use App\Models\Team;
use App\Models\TourAssignment;
use App\Models\TourRewardGrant;
use App\Models\User;
use App\Models\UserPoint;
use App\Services\Profile\ProfileTourCompletionService;
use App\Services\Profile\SeriesProfileCompletion;
use App\Services\TourAssignmentService;
use App\Services\TourRegistry;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ProfileTourCompletionTest extends TestCase
{
    use RefreshDatabase;

    private function member(bool $complete = true): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::Mitglied->value]);
        if ($complete) {
            $user->forceFill(array_fill_keys(array_keys(SeriesProfileCompletion::FIELDS), 'Gespeicherter Favorit'))->save();
        }

        return $user;
    }

    private function assignment(User $user, bool $started = true): TourAssignment
    {
        $service = app(TourAssignmentService::class);
        $assignment = $service->assignIfMissing($user, 'profilpflege', TourAssignmentSource::System);
        if ($started) {
            $assignment = $service->start($assignment);
            $assignment = $service->rememberProgress($assignment, 'profile-complete');
        }

        return $assignment;
    }

    public function test_first_completion_books_five_points_in_members_team_and_retry_is_stable(): void
    {
        $user = $this->member();
        $assignment = $this->assignment($user);
        $otherTeam = Team::factory()->create();
        $user->forceFill(['current_team_id' => $otherTeam->id])->save();
        $this->actingAs($user)->postJson(route('touren.complete', $assignment))
            ->assertOk()->assertJsonPath('reward.points_awarded', 5)->assertJsonPath('reward.status', 'awarded');
        $completedAt = $assignment->fresh()->completed_at;
        $this->travel(2)->minutes();
        $user->forceFill(['lieblingsmutation' => null])->save();
        $this->postJson(route('touren.complete', $assignment))->assertOk()->assertJsonPath('reward.points_awarded', 0);
        $this->assertTrue($completedAt->equalTo($assignment->fresh()->completed_at));
        $this->assertDatabaseHas('user_points', ['user_id' => $user->id, 'team_id' => Team::membersTeam()->id, 'points' => 5]);
        $this->assertSame(1, TourRewardGrant::where('user_id', $user->id)->count());
        $this->assertSame(1, UserPoint::where('user_id', $user->id)->count());
    }

    public static function missingFields(): iterable
    {
        foreach (array_keys(SeriesProfileCompletion::FIELDS) as $field) {
            foreach ([null, '', " \t\n\u{00a0}"] as $index => $value) {
                yield $field.'-'.$index => [$field, $value];
            }
        }
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_reward_status_reads_grants_without_schema_lookups(bool $rewardReceived): void
    {
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
            $this->assertSame($rewardReceived, $overview['reward_received']);
            $this->assertSame($rewardReceived, $payload['reward_received']);
            $schemaQueries = collect(DB::getQueryLog())->pluck('query')->filter(
                fn (string $sql) => preg_match('/sqlite_(?:master|schema)|information_schema|pg_catalog|pragma/i', $sql),
            );
            $this->assertEmpty($schemaQueries, 'Building reward status must not query schema metadata.');
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    #[TestWith(['overview'])]
    #[TestWith(['payload'])]
    public function test_missing_reward_grants_migration_is_not_reported_as_unrewarded(string $surface): void
    {
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
    }

    public function test_completion_results_keep_assignments_persistable_without_response_attributes(): void
    {
        $user = $this->member();
        $assignment = $this->assignment($user);
        $service = app(TourAssignmentService::class);

        foreach (['first', 'retry', 'restart'] as $attempt) {
            if ($attempt === 'restart') {
                $service->reassign($user, 'profilpflege', TourAssignmentSource::SelfService);
                $assignment = $this->assignment($user);
            }

            $result = $service->complete($assignment);
            $this->assertSame([
                'status' => $attempt === 'first' ? 'awarded' : 'already_awarded',
                'points_awarded' => $attempt === 'first' ? 5 : 0,
            ], $result->reward);
            $this->assertTrue($result->assignment->is($assignment));
            $this->assertSame(TourAssignmentStatus::Completed, $result->assignment->status);
            $this->assertArrayNotHasKey('reward_result', $result->assignment->getAttributes());
            $result->assignment->forceFill(['metadata' => ['completion_attempt' => $attempt]])->save();
            $this->assertSame(['completion_attempt' => $attempt], $assignment->fresh()->metadata);
        }

        $this->assertSame(1, UserPoint::where('user_id', $user->id)->count());
        $this->assertSame(1, TourRewardGrant::where('user_id', $user->id)->count());
    }

    #[DataProvider('missingFields')]
    public function test_every_missing_saved_field_prevents_completion(string $field, ?string $value): void
    {
        $user = $this->member();
        $assignment = $this->assignment($user);
        $user->forceFill([$field => $value])->save();
        $this->actingAs($user)->postJson(route('touren.complete', $assignment), [
            'complete' => true, 'filled' => 11, $field => 'Nur im Browser', 'points' => 999,
        ])->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame(TourAssignmentStatus::InProgress, $assignment->fresh()->status);
        $this->assertDatabaseMissing('tour_reward_grants', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('user_points', ['user_id' => $user->id]);
    }

    public function test_incomplete_tour_can_be_resumed_and_rewarded_later(): void
    {
        $user = $this->member(false);
        $assignment = $this->assignment($user);
        $this->actingAs($user)->postJson(route('touren.complete', $assignment))->assertUnprocessable();
        $this->postJson(route('touren.dismiss', $assignment))->assertOk();
        $this->post(route('touren.resume', $assignment))->assertRedirect(route('profile.show'));
        $this->assertSame('profile-complete', $assignment->fresh()->current_step_key);
        $user->forceFill(array_fill_keys(array_keys(SeriesProfileCompletion::FIELDS), 'Favorit'))->save();
        $this->postJson(route('touren.complete', $assignment))->assertOk()->assertJsonPath('reward.points_awarded', 5);
    }

    public function test_unstarted_tour_or_missing_completion_step_cannot_claim_reward(): void
    {
        $user = $this->member();
        $assignment = $this->assignment($user, false);
        $this->actingAs($user)->postJson(route('touren.complete', $assignment))->assertUnprocessable();
        $this->postJson(route('touren.progress', $assignment), ['step_key' => 'profile-complete'])->assertUnprocessable();
        $this->postJson(route('touren.start', $assignment))->assertOk();
        $this->postJson(route('touren.complete', $assignment))->assertUnprocessable();
        $this->assertDatabaseMissing('user_points', ['user_id' => $user->id]);
    }

    public function test_previous_completion_does_not_exclude_reward_but_stale_request_is_rejected(): void
    {
        $user = $this->member();
        $old = $this->assignment($user);
        $old->forceFill(['tour_version' => 2, 'status' => TourAssignmentStatus::Completed, 'completed_at' => now()])->save();
        $this->actingAs($user)->postJson(route('touren.complete', $old))->assertStatus(409);
        $current = $this->assignment($user);
        $this->postJson(route('touren.complete', $current))->assertOk()->assertJsonPath('reward.points_awarded', 5);
    }

    public function test_restarts_and_later_versions_never_pay_again(): void
    {
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
        $this->assertSame(5, (int) UserPoint::where('user_id', $user->id)->sum('points'));
    }

    public function test_foreign_or_no_longer_active_member_cannot_claim(): void
    {
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
            $this->assertSame(403, $error->getStatusCode());
        }
        $this->assertDatabaseMissing('user_points', ['user_id' => $owner->id]);
    }

    public function test_grant_failure_rolls_back_booking_and_completion_and_allows_retry(): void
    {
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
            $this->assertSame('Simulated grant failure', $error->getMessage());
        } finally {
            $fail = false;
        }
        $this->assertDatabaseMissing('user_points', ['user_id' => $user->id]);
        $this->assertSame(TourAssignmentStatus::InProgress, $assignment->fresh()->status);
        $this->actingAs($user)->postJson(route('touren.complete', $assignment))->assertOk()->assertJsonPath('reward.points_awarded', 5);
    }

    public function test_deleted_booking_or_assignment_does_not_remove_once_only_proof(): void
    {
        $user = $this->member();
        $assignment = $this->assignment($user);
        app(TourAssignmentService::class)->complete($assignment);
        UserPoint::where('user_id', $user->id)->firstOrFail()->delete();
        $assignment->delete();
        $grant = TourRewardGrant::where('user_id', $user->id)->firstOrFail();
        $this->assertNull($grant->user_point_id);
        $this->assertNull($grant->tour_assignment_id);
        $this->actingAs($user)->postJson(route('touren.complete', $this->assignment($user)))->assertOk()->assertJsonPath('reward.points_awarded', 0);
        $migration = require database_path('migrations/2026_10_25_120000_create_tour_reward_grants_table.php');
        $this->expectException(RuntimeException::class);
        $migration->down();
    }

    public function test_invitation_does_not_start_tour_and_resume_removes_suppression(): void
    {
        $user = $this->member();
        $assignment = $this->assignment($user, false);
        $this->actingAs($user)->getJson(route('touren.current'))->assertOk()
            ->assertJsonPath('tour.start_mode', 'invitation')->assertJsonPath('tour.active_in_session', false)
            ->assertJsonPath('tour.profile.filled', 11)->assertJsonPath('tour.reward_received', false)
            ->assertJsonPath('tour.reward_points', ProfileTourCompletionService::POINTS);
        $this->assertNull($assignment->fresh()->started_at);
        $this->postJson(route('touren.dismiss', $assignment))->assertOk();
        $this->getJson(route('touren.current'))->assertJsonPath('tour', null);
        $this->post(route('touren.resume', $assignment))->assertRedirect(route('profile.show'));
        $this->getJson(route('touren.current'))->assertJsonPath('tour.active_in_session', true);
    }

    public function test_old_open_assignment_does_not_reset_current_completed_assignment(): void
    {
        $user = $this->member();
        $current = $this->assignment($user);
        app(TourAssignmentService::class)->complete($current);
        $old = $current->replicate();
        $old->forceFill(['tour_version' => 2, 'status' => TourAssignmentStatus::Pending, 'completed_at' => null])->save();
        $resolved = app(TourAssignmentService::class)->activeAssignment($old);
        $this->assertSame($current->id, $resolved->id);
        $this->assertSame(TourAssignmentStatus::Completed, $resolved->status);
        $this->assertNull(app(TourAssignmentService::class)->currentPromptableAssignmentForUser($user));
        $this->assertSame(1, DB::table('tour_reward_grants')->where('user_id', $user->id)->count());
    }
}
