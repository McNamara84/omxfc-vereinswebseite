<?php

namespace Tests\Feature;

use App\Enums\MaddraxikonContributionStatus;
use App\Enums\MaddraxikonContributionType;
use App\Enums\MaddraxikonRewardEventStatus;
use App\Enums\Role;
use App\Models\BaxxEarningRule;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonContribution;
use App\Models\MaddraxikonRewardEvent;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPoint;
use App\Services\MembersTeamMembershipLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tests\TestCase;

use function array_filter;
use function base_path;
use function config;
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

#[CoversClass(MembersTeamMembershipLock::class)]
class MembersTeamMembershipMariaDbConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private bool $committedTestDatabase = false;

    public function test_pending_demotion_serializes_against_maddraxikon_reward_decision(): void
    {
        $this->requireSafeMariaDbTestDatabase();

        $team = Team::membersTeam();
        $this->assertInstanceOf(Team::class, $team);

        $member = User::factory()->create([
            'current_team_id' => $team->id,
        ]);
        $team->users()->attach($member, [
            'role' => Role::Mitglied->value,
        ]);
        $verifiedAt = now()->subDays(2);
        $link = MaddraxikonAccountLink::factory()->create([
            'user_id' => $member->id,
            'verified_at' => $verifiedAt,
            'first_verified_at' => $verifiedAt,
            'consented_at' => $verifiedAt,
        ]);
        $occurredAt = now()->subDay();
        $contribution = MaddraxikonContribution::factory()
            ->newArticle()
            ->create([
                'wiki_key' => $link->wiki_key,
                'wiki_user_id' => $link->wiki_user_id,
                'wiki_username' => $link->wiki_username,
                'account_link_id' => $link->id,
                'user_id' => $member->id,
                'namespace_id' => 0,
                'bot' => false,
                'anonymous' => false,
                'redirect' => false,
                'user_hidden' => false,
                'old_size' => 0,
                'new_size' => 1_000,
                'occurred_at' => $occurredAt,
                'eligible_after' => now()->subMinute(),
                'status' => MaddraxikonContributionStatus::Pending,
                'type' => MaddraxikonContributionType::New,
            ]);

        BaxxEarningRule::query()->updateOrCreate(
            ['action_key' => MaddraxikonRewardEvent::ACTION_NEW_ARTICLE],
            [
                'label' => 'Maddraxikon-Artikel',
                'description' => 'Concurrency-Testregel',
                'points' => 5,
                'every_count' => 1,
                'is_active' => true,
            ]
        );

        $this->assertSame(1, DB::transactionLevel());
        DB::connection()->commit();
        $this->committedTestDatabase = true;

        $holder = $this->startWorker(
            'hold-demotion',
            $member->id,
            $contribution->id
        );
        $holderFinished = false;

        try {
            $locked = $this->readWorkerResult($holder, 10);
            $this->assertTrue($locked['ok'] ?? false, $this->workerFailure($locked));
            $this->assertSame('demotion_locked', $locked['event'] ?? null);

            $blockedEvaluator = $this->startWorker(
                'evaluate',
                $member->id,
                $contribution->id
            );

            try {
                $blocked = $this->readWorkerResult($blockedEvaluator, 15);
                $blockedExitCode = $this->finishWorker($blockedEvaluator);
            } finally {
                $this->terminateWorker($blockedEvaluator);
            }

            $this->assertSame(0, $blockedExitCode, $this->workerFailure($blocked));
            $this->assertTrue($blocked['ok'] ?? false, $this->workerFailure($blocked));
            $this->assertSame(
                MaddraxikonContributionStatus::Pending->value,
                $blocked['status'] ?? null
            );
            $this->assertSame(0, $blocked['written'] ?? null);
            $this->assertGreaterThanOrEqual(
                1,
                (int) ($blocked['evaluation_attempts'] ?? 0)
            );
            $this->assertMatchesRegularExpression(
                '/(?:DeadlockException|QueryException): SQLSTATE\[/',
                (string) ($blocked['last_evaluation_error'] ?? ''),
            );

            $this->assertDatabaseHas('team_user', [
                'team_id' => $team->id,
                'user_id' => $member->id,
                'role' => Role::Mitglied->value,
            ]);
            $this->assertDatabaseMissing('user_points', [
                'user_id' => $member->id,
                'team_id' => $team->id,
            ]);

            $this->sendWorkerCommand($holder, 'COMMIT');
            $committed = $this->readWorkerResult($holder, 10);
            $this->assertTrue(
                $committed['ok'] ?? false,
                $this->workerFailure($committed)
            );
            $this->assertSame(
                'demotion_committed',
                $committed['event'] ?? null
            );
            $this->assertSame(0, $this->finishWorker($holder));
            $holderFinished = true;

            $finalEvaluator = $this->startWorker(
                'evaluate',
                $member->id,
                $contribution->id
            );

            try {
                $final = $this->readWorkerResult($finalEvaluator, 10);
                $finalExitCode = $this->finishWorker($finalEvaluator);
            } finally {
                $this->terminateWorker($finalEvaluator);
            }

            $this->assertSame(0, $finalExitCode, $this->workerFailure($final));
            $this->assertTrue($final['ok'] ?? false, $this->workerFailure($final));
            $this->assertSame(1, $final['written'] ?? null);
            $this->assertSame(
                MaddraxikonContributionStatus::Rejected->value,
                $final['status'] ?? null
            );
            $this->assertSame(
                'membership_inactive',
                $final['status_reason'] ?? null
            );

            $this->assertDatabaseHas('team_user', [
                'team_id' => $team->id,
                'user_id' => $member->id,
                'role' => Role::Anwaerter->value,
            ]);
            $this->assertDatabaseHas('maddraxikon_reward_events', [
                'source_contribution_id' => $contribution->id,
                'status' => MaddraxikonRewardEventStatus::Rejected->value,
                'status_reason' => 'membership_inactive',
                'awarded_points' => 0,
            ]);
            $this->assertSame(
                0,
                UserPoint::query()
                    ->where('user_id', $member->id)
                    ->where('team_id', $team->id)
                    ->sum('points')
            );
        } finally {
            if (! $holderFinished) {
                $this->sendWorkerCommand($holder, 'ROLLBACK');
                $this->terminateWorker($holder);
            }
        }
    }

    protected function tearDown(): void
    {
        try {
            if (
                $this->committedTestDatabase
                && isset($this->app)
            ) {
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

    private function requireSafeMariaDbTestDatabase(): void
    {
        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");
        $database = (string) config(
            "database.connections.{$connection}.database"
        );

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped(
                'Der echte Nebenlaeufigkeitstest benoetigt MariaDB.'
            );
        }

        if (! str_contains($database, '_test')) {
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
                'Der echte Nebenlaeufigkeitstest wird nur auf MariaDB ausgefuehrt.'
            );
        }

        if (! function_exists('proc_open')) {
            $this->markTestSkipped(
                'proc_open wird fuer die getrennten Worker-Prozesse benoetigt.'
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
        int $contributionId
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
                    'tests/Support/MariaDbMembershipConcurrencyWorker.php'
                ),
                $mode,
                (string) $userId,
                (string) $contributionId,
            ],
            $descriptors,
            $pipes,
            base_path(),
            null,
            ['bypass_shell' => true]
        );

        if (! is_resource($process) || count($pipes) !== 3) {
            throw new RuntimeException(
                'Der MariaDB-Nebenlaeufigkeits-Worker konnte nicht starten.'
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
                    'Zeitueberschreitung beim Warten auf den MariaDB-Worker.'
                );
            }

            $line = fgets($worker['stdout']);

            if ($line === false) {
                $stderr = trim(
                    (string) stream_get_contents($worker['stderr'])
                );

                throw new RuntimeException(
                    'Der MariaDB-Worker endete ohne Ergebnis. '.$stderr
                );
            }
        } while (
            trim($line) === ''
            && microtime(true) < $deadline
        );

        if (trim($line) === '') {
            throw new RuntimeException(
                'Der MariaDB-Worker lieferte nur leere Ausgabe.'
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
                'Ungueltige Worker-Ausgabe: stdout=%s stderr=%s',
                trim($line),
                $stderr
            ), previous: $exception);
        }

        if (! is_array($result)) {
            throw new RuntimeException(
                'Der MariaDB-Worker lieferte kein JSON-Objekt.'
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
        ])) ?: 'Der MariaDB-Worker meldete keinen Fehlertext.';
    }
}
