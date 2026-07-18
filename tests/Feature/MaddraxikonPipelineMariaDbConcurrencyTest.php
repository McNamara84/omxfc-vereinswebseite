<?php

namespace Tests\Feature;

use App\Enums\MaddraxikonContributionStatus;
use App\Enums\MaddraxikonRewardEventStatus;
use App\Enums\Role;
use App\Models\BaxxEarningRule;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonContribution;
use App\Models\MaddraxikonRewardEvent;
use App\Models\MaddraxikonSyncState;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPoint;
use App\Services\Maddraxikon\MaddraxikonContributionImporter;
use App\Services\Maddraxikon\MaddraxikonRewardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tests\TestCase;

use function array_filter;
use function array_sum;
use function base_path;
use function config;
use function count;
use function fclose;
use function fflush;
use function fgets;
use function function_exists;
use function fwrite;
use function implode;
use function in_array;
use function intdiv;
use function is_array;
use function is_resource;
use function json_decode;
use function microtime;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function proc_terminate;
use function sprintf;
use function str_contains;
use function stream_get_contents;
use function stream_select;
use function trim;

#[CoversClass(MaddraxikonContributionImporter::class)]
#[CoversClass(MaddraxikonRewardService::class)]
class MaddraxikonPipelineMariaDbConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private bool $committedTestDatabase = false;

    public function test_two_evaluators_award_the_same_source_exactly_once(): void
    {
        $this->requireSafeMariaDbTestDatabase();

        [$team, $user, $link] = $this->linkedMember();
        MaddraxikonSyncState::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'watermark_at' => now(),
            'last_succeeded_at' => now(),
        ]);
        BaxxEarningRule::query()->updateOrCreate(
            ['action_key' => MaddraxikonRewardEvent::ACTION_NEW_ARTICLE],
            [
                'label' => 'Maddraxikon-Artikel',
                'description' => 'MariaDB-Paralleltest',
                'points' => 5,
                'every_count' => 1,
                'is_active' => true,
            ]
        );
        $contribution = MaddraxikonContribution::factory()
            ->newArticle()
            ->create([
                'wiki_key' => $link->wiki_key,
                'revision_id' => 91_000_001,
                'rc_id' => 91_020_001,
                'page_id' => 91_010_001,
                'wiki_user_id' => $link->wiki_user_id,
                'wiki_username' => $link->wiki_username,
                'account_link_id' => $link->id,
                'user_id' => $user->id,
                'occurred_at' => now()->subDay(),
                'eligible_after' => now()->subMinute(),
                'old_size' => 0,
                'new_size' => 1_000,
                'status' => MaddraxikonContributionStatus::Pending,
            ]);

        $this->commitFixturesForWorkers();

        $workers = [
            $this->startWorker('evaluate', $user->id, $contribution->id),
            $this->startWorker('evaluate', $user->id, $contribution->id),
        ];
        $results = [];

        try {
            foreach ($workers as $worker) {
                $ready = $this->readWorkerResult($worker, 15);
                $this->assertTrue(
                    $ready['ok'] ?? false,
                    $this->workerFailure($ready)
                );
                $this->assertSame(
                    'evaluator_ready',
                    $ready['event'] ?? null
                );
            }

            foreach ($workers as $worker) {
                $this->sendWorkerCommand($worker, 'EVALUATE');
            }

            foreach ($workers as $index => $worker) {
                $results[$index] = $this->readWorkerResult($worker, 20);
            }

            foreach ($workers as $index => &$worker) {
                $exitCode = $this->finishWorker($worker);
                $this->assertSame(
                    0,
                    $exitCode,
                    $this->workerFailure($results[$index])
                );
            }
            unset($worker);
        } finally {
            foreach ($workers as &$worker) {
                $this->terminateWorker($worker);
            }
            unset($worker);
        }

        foreach ($results as $result) {
            $this->assertTrue(
                $result['ok'] ?? false,
                $this->workerFailure($result)
            );
            $this->assertSame(
                'evaluation_finished',
                $result['event'] ?? null
            );
        }

        $this->assertSame(
            1,
            array_sum(array_map(
                static fn (array $result): int => (int) $result['written'],
                $results
            ))
        );
        $this->assertDatabaseCount('maddraxikon_reward_events', 1);
        $this->assertDatabaseHas('maddraxikon_reward_events', [
            'wiki_key' => 'maddraxikon-de',
            'source_contribution_id' => $contribution->id,
            'source_key' => 'new:'.$contribution->revision_id,
            'status' => MaddraxikonRewardEventStatus::Awarded->value,
            'awarded_points' => 5,
        ]);
        $this->assertDatabaseCount('user_points', 1);
        $this->assertSame(
            5,
            (int) UserPoint::query()
                ->where('user_id', $user->id)
                ->where('team_id', $team->id)
                ->sum('points')
        );
        $this->assertSame(
            MaddraxikonContributionStatus::Awarded,
            $contribution->fresh()->status
        );
    }

    public function test_two_importers_persist_the_same_recent_change_exactly_once(): void
    {
        $this->requireSafeMariaDbTestDatabase();

        [, $user, $link] = $this->linkedMember();
        MaddraxikonSyncState::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'watermark_at' => now()->subHour(),
            'initial_watermark_at' => now()->subDay(),
            'last_succeeded_at' => now()->subHour(),
            'run_sequence' => 0,
        ]);
        $revisionId = 92_000_001;

        $this->commitFixturesForWorkers();

        $workers = [
            $this->startWorker('import', $user->id, $revisionId),
            $this->startWorker('import', $user->id, $revisionId),
        ];
        $results = [];

        try {
            foreach ($workers as $worker) {
                $ready = $this->readWorkerResult($worker, 15);
                $this->assertTrue(
                    $ready['ok'] ?? false,
                    $this->workerFailure($ready)
                );
                $this->assertSame(
                    'import_api_ready',
                    $ready['event'] ?? null
                );
            }

            foreach ($workers as $worker) {
                $this->sendWorkerCommand($worker, 'PERSIST');
            }

            foreach ($workers as $index => $worker) {
                $results[$index] = $this->readWorkerResult($worker, 20);
            }

            foreach ($workers as $index => &$worker) {
                $exitCode = $this->finishWorker($worker);
                $this->assertSame(
                    0,
                    $exitCode,
                    $this->workerFailure($results[$index])
                );
            }
            unset($worker);
        } finally {
            foreach ($workers as &$worker) {
                $this->terminateWorker($worker);
            }
            unset($worker);
        }

        foreach ($results as $result) {
            $this->assertTrue(
                $result['ok'] ?? false,
                $this->workerFailure($result)
            );
            $this->assertSame('import_finished', $result['event'] ?? null);
        }

        $this->assertSame(
            1,
            array_sum(array_map(
                static fn (array $result): int => (int) $result['written'],
                $results
            ))
        );
        $this->assertDatabaseCount('maddraxikon_contributions', 1);
        $this->assertDatabaseHas('maddraxikon_contributions', [
            'wiki_key' => 'maddraxikon-de',
            'revision_id' => $revisionId,
            'account_link_id' => $link->id,
            'user_id' => $user->id,
            'status' => MaddraxikonContributionStatus::Pending->value,
        ]);

        $state = MaddraxikonSyncState::query()
            ->where('wiki_key', 'maddraxikon-de')
            ->firstOrFail();
        $this->assertSame(2, $state->run_sequence);
        $this->assertSame($revisionId + 20_000, $state->last_seen_rc_id);
    }

    protected function tearDown(): void
    {
        try {
            if ($this->committedTestDatabase && isset($this->app)) {
                $exitCode = $this->artisan('migrate:fresh')->execute();

                if ($exitCode !== 0) {
                    throw new RuntimeException(sprintf(
                        'Die MariaDB-Testdatenbank konnte nicht bereinigt werden (Exit %d).',
                        $exitCode
                    ));
                }
            }
        } finally {
            parent::tearDown();
        }
    }

    /**
     * @return array{Team, User, MaddraxikonAccountLink}
     */
    private function linkedMember(): array
    {
        Team::clearMembersTeamCache();
        $team = Team::membersTeam();

        if (! $team) {
            throw new RuntimeException(
                'Das Mitglieder-Team fehlt im MariaDB-Paralleltest.'
            );
        }

        $user = User::factory()->create([
            'current_team_id' => $team->id,
        ]);
        $team->users()->attach($user, [
            'role' => Role::Mitglied->value,
        ]);
        $verifiedAt = now()->subDays(2);
        $link = MaddraxikonAccountLink::factory()->create([
            'user_id' => $user->id,
            'verified_at' => $verifiedAt,
            'first_verified_at' => $verifiedAt,
            'consented_at' => $verifiedAt,
        ]);

        return [$team, $user, $link];
    }

    private function commitFixturesForWorkers(): void
    {
        $this->assertSame(1, DB::transactionLevel());
        DB::connection()->commit();
        $this->committedTestDatabase = true;
    }

    private function requireSafeMariaDbTestDatabase(): void
    {
        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");
        $database = (string) config(
            "database.connections.{$connection}.database"
        );

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped(
                'Der echte Pipeline-Nebenläufigkeitstest benötigt MariaDB.'
            );
        }

        if ($database !== 'omxfc_maddraxikon_test') {
            throw new RuntimeException(sprintf(
                'Unsichere Testdatenbank verweigert: %s.',
                $database
            ));
        }

        $version = (string) (DB::selectOne(
            'SELECT VERSION() AS database_version'
        )->database_version ?? '');

        if (! str_contains($version, 'MariaDB')) {
            $this->markTestSkipped(
                'Der echte Pipeline-Nebenläufigkeitstest läuft nur auf MariaDB.'
            );
        }

        if (! function_exists('proc_open')) {
            $this->markTestSkipped(
                'proc_open wird für getrennte Worker-Prozesse benötigt.'
            );
        }
    }

    /**
     * @return array{
     *     process: resource,
     *     stdin: resource,
     *     stdout: resource,
     *     stderr: resource
     * }
     */
    private function startWorker(
        string $mode,
        int $userId,
        int $externalId
    ): array {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $pipes = [];
        $process = proc_open(
            [
                PHP_BINARY,
                base_path(
                    'tests/Support/MariaDbMaddraxikonPipelineConcurrencyWorker.php'
                ),
                $mode,
                (string) $userId,
                (string) $externalId,
            ],
            $descriptors,
            $pipes,
            base_path(),
            null,
            ['bypass_shell' => true]
        );

        if (! is_resource($process) || count($pipes) !== 3) {
            throw new RuntimeException(
                'Der MariaDB-Pipeline-Worker konnte nicht starten.'
            );
        }

        return [
            'process' => $process,
            'stdin' => $pipes[0],
            'stdout' => $pipes[1],
            'stderr' => $pipes[2],
        ];
    }

    /**
     * @param  array{
     *     process: resource,
     *     stdin: resource,
     *     stdout: resource,
     *     stderr: resource
     * }  $worker
     * @return array<string, mixed>
     */
    private function readWorkerResult(array $worker, int $timeoutSeconds): array
    {
        $deadline = microtime(true) + $timeoutSeconds;
        $line = null;

        do {
            $remainingMicroseconds = (int) max(
                0,
                ($deadline - microtime(true)) * 1_000_000
            );
            $read = [$worker['stdout']];
            $write = null;
            $except = null;
            $ready = stream_select(
                $read,
                $write,
                $except,
                intdiv($remainingMicroseconds, 1_000_000),
                $remainingMicroseconds % 1_000_000
            );

            if ($ready !== 1) {
                throw new RuntimeException(
                    'Zeitüberschreitung beim Warten auf den MariaDB-Pipeline-Worker.'
                );
            }

            $line = fgets($worker['stdout']);

            if ($line === false) {
                $stderr = trim(
                    (string) stream_get_contents($worker['stderr'])
                );

                throw new RuntimeException(
                    'Der MariaDB-Pipeline-Worker endete ohne Ergebnis. '.$stderr
                );
            }
        } while (
            trim($line) === ''
            && microtime(true) < $deadline
        );

        if (trim($line) === '') {
            throw new RuntimeException(
                'Der MariaDB-Pipeline-Worker lieferte nur leere Ausgabe.'
            );
        }

        try {
            $result = json_decode(
                trim($line),
                true,
                flags: JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            $stderr = trim((string) stream_get_contents($worker['stderr']));

            throw new RuntimeException(sprintf(
                'Ungültige Worker-Ausgabe: stdout=%s stderr=%s',
                trim($line),
                $stderr
            ), previous: $exception);
        }

        if (! is_array($result)) {
            throw new RuntimeException(
                'Der MariaDB-Pipeline-Worker lieferte kein JSON-Objekt.'
            );
        }

        return $result;
    }

    /**
     * @param  array{stdin: resource}  $worker
     */
    private function sendWorkerCommand(array $worker, string $command): void
    {
        if (
            ! isset($worker['stdin'])
            || ! is_resource($worker['stdin'])
        ) {
            return;
        }

        $written = @fwrite($worker['stdin'], $command.PHP_EOL);

        if ($written === false) {
            return;
        }

        @fflush($worker['stdin']);
    }

    /**
     * @param  array{
     *     process: resource,
     *     stdin: resource,
     *     stdout: resource,
     *     stderr: resource
     * }  $worker
     */
    private function finishWorker(array &$worker): int
    {
        foreach (['stdin', 'stdout', 'stderr'] as $pipe) {
            if (is_resource($worker[$pipe])) {
                fclose($worker[$pipe]);
            }
        }

        if (! is_resource($worker['process'])) {
            return -1;
        }

        $exitCode = proc_close($worker['process']);
        $worker = [];

        return $exitCode;
    }

    /**
     * @param  array{
     *     process?: resource,
     *     stdin?: resource,
     *     stdout?: resource,
     *     stderr?: resource
     * }  $worker
     */
    private function terminateWorker(array &$worker): void
    {
        if ($worker === []) {
            return;
        }

        foreach (['stdin', 'stdout', 'stderr'] as $pipe) {
            if (isset($worker[$pipe]) && is_resource($worker[$pipe])) {
                fclose($worker[$pipe]);
            }
        }

        if (
            isset($worker['process'])
            && is_resource($worker['process'])
        ) {
            $status = proc_get_status($worker['process']);

            if ($status['running'] ?? false) {
                proc_terminate($worker['process']);
            }

            proc_close($worker['process']);
        }

        $worker = [];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function workerFailure(array $result): string
    {
        return implode(' ', array_filter([
            (string) ($result['exception'] ?? ''),
            (string) ($result['message'] ?? ''),
        ])) ?: 'Der MariaDB-Pipeline-Worker meldete keinen Fehlertext.';
    }
}
