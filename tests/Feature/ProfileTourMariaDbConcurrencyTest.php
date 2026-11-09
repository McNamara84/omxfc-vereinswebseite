<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TourAssignmentSource;
use App\Models\Team;
use App\Models\TourRewardGrant;
use App\Models\User;
use App\Models\UserPoint;
use App\Services\Profile\SeriesProfileCompletion;
use App\Services\TourAssignmentService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(ProfileTourMariaDbConcurrencyTestFixtures::class);
dataset('ProfileTourMariaDbConcurrencyTest.competingActions', static fn (): array => [['profile-complete'], ['profile-restart'], ['profile-empty'], ['profile-revoke']]);
beforeEach(function (): void {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Benötigt echte MariaDB-Verbindungen.');
    }
    if (DB::connection()->getDatabaseName() !== 'omxfc_maddraxikon_test') {
        throw new RuntimeException('Unsichere Testdatenbank verweigert.');
    }
});
afterEach(function (): void {
    try {
        if ($this->committed) {
            $this->artisan('migrate:fresh')->assertExitCode(0);
        }
    } finally {
    }
});
test('completion is atomic against concurrent action', function (string $action): void {
    $team = Team::membersTeam();
    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member, ['role' => Role::Mitglied->value]);
    $member->forceFill(array_fill_keys(array_keys(SeriesProfileCompletion::FIELDS), 'Gespeicherter Favorit'))->save();
    $service = app(TourAssignmentService::class);
    $assignment = $service->assignIfMissing($member, 'profilpflege', TourAssignmentSource::System);
    $assignment = $service->rememberProgress($service->start($assignment), 'profile-complete');
    $results = $this->race([['profile-complete', $member->id, $assignment->id], [$action, $member->id, $assignment->id]]);
    $expected = $results[0] === 'ok' ? 5 : 0;
    expect((int) UserPoint::where('user_id', $member->id)->sum('points'))->toBe($expected)
        ->and(TourRewardGrant::where('user_id', $member->id)->count())->toBe($expected > 0 ? 1 : 0);
    if ($action === 'profile-complete') {
        expect($results)->toBe(['ok', 'ok'])
            ->and($expected)->toBe(5);
    }
    if ($action === 'profile-restart') {
        $assignment = $service->rememberProgress($service->start($assignment), 'profile-complete');
        $service->complete($assignment);
        expect((int) UserPoint::where('user_id', $member->id)->sum('points'))->toBe(5)
            ->and(TourRewardGrant::where('user_id', $member->id)->count())->toBe(1);
    }
})->with('ProfileTourMariaDbConcurrencyTest.competingActions');
