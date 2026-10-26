<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TourAssignmentSource;
use App\Models\Team;
use App\Models\TourRewardGrant;
use App\Models\User;
use App\Models\UserPoint;
use App\Services\Profile\SeriesProfileCompletion;
use App\Services\TourAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ProfileTourMariaDbConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private bool $committed = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Benötigt echte MariaDB-Verbindungen.');
        }
        if (DB::connection()->getDatabaseName() !== 'omxfc_maddraxikon_test') {
            throw new RuntimeException('Unsichere Testdatenbank verweigert.');
        }
    }

    private function race(array $commands): array
    {
        DB::connection()->commit();
        $this->committed = true;
        $workers = [];
        try {
            foreach ($commands as $command) {
                $process = proc_open([PHP_BINARY, base_path('tests/Support/MariaDbBaxxWorker.php'), ...array_map(strval(...), $command)],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
                if (! is_resource($process)) {
                    throw new RuntimeException('Worker konnte nicht starten.');
                }
                stream_set_timeout($pipes[1], 30);
                $workers[] = [$process, $pipes];
            }
            foreach ($workers as [$process, $pipes]) {
                $this->assertSame("ready\n", fgets($pipes[1]));
            }
            foreach ($workers as [$process, $pipes]) {
                fwrite($pipes[0], "RUN\n");
                fflush($pipes[0]);
            }
            $results = [];
            foreach ($workers as [$process, $pipes]) {
                $line = fgets($pipes[1]);
                $this->assertNotFalse($line, stream_get_contents($pipes[2]));
                $results[] = json_decode($line, true, flags: JSON_THROW_ON_ERROR)['result'];
            }

            return $results;
        } finally {
            foreach ($workers as [$process, $pipes]) {
                foreach ($pipes as $pipe) {
                    fclose($pipe);
                }
                if (proc_get_status($process)['running']) {
                    proc_terminate($process);
                }
                proc_close($process);
            }
        }
    }

    public static function competingActions(): array
    {
        return [['profile-complete'], ['profile-restart'], ['profile-empty'], ['profile-revoke']];
    }

    #[DataProvider('competingActions')]
    public function test_completion_is_atomic_against_concurrent_action(string $action): void
    {
        $team = Team::membersTeam();
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member, ['role' => Role::Mitglied->value]);
        $member->forceFill(array_fill_keys(array_keys(SeriesProfileCompletion::FIELDS), 'Gespeicherter Favorit'))->save();
        $service = app(TourAssignmentService::class);
        $assignment = $service->assignIfMissing($member, 'profilpflege', TourAssignmentSource::System);
        $assignment = $service->rememberProgress($service->start($assignment), 'profile-complete');
        $results = $this->race([['profile-complete', $member->id, $assignment->id], [$action, $member->id, $assignment->id]]);
        $expected = $results[0] === 'ok' ? 5 : 0;
        $this->assertSame($expected, (int) UserPoint::where('user_id', $member->id)->sum('points'));
        $this->assertSame($expected > 0 ? 1 : 0, TourRewardGrant::where('user_id', $member->id)->count());
        if ($action === 'profile-complete') {
            $this->assertSame(['ok', 'ok'], $results);
            $this->assertSame(5, $expected);
        }
        if ($action === 'profile-restart') {
            $assignment = $service->rememberProgress($service->start($assignment), 'profile-complete');
            $service->complete($assignment);
            $this->assertSame(5, (int) UserPoint::where('user_id', $member->id)->sum('points'));
            $this->assertSame(1, TourRewardGrant::where('user_id', $member->id)->count());
        }
    }

    protected function tearDown(): void
    {
        try {
            if ($this->committed) {
                $this->artisan('migrate:fresh')->assertExitCode(0);
            }
        } finally {
            parent::tearDown();
        }
    }
}
