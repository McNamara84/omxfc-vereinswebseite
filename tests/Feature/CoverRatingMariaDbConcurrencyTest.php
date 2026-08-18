<?php

namespace Tests\Feature;

use App\Enums\BookType;
use App\Enums\Role;
use App\Models\BaxxEarningProgress;
use App\Models\BaxxEarningRule;
use App\Models\Book;
use App\Models\BookCover;
use App\Models\CoverRating;
use App\Models\UserPoint;
use App\Services\CoverRatings\CoverRatingBaxxService;
use App\Services\CoverRatings\CoverRatingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

#[CoversClass(CoverRatingService::class)]
#[CoversClass(CoverRatingBaxxService::class)]
class CoverRatingMariaDbConcurrencyTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private bool $committedTestDatabase = false;

    public function test_two_parallel_ratings_cross_the_hundred_boundary_exactly_once(): void
    {
        $this->requireSafeMariaDbTestDatabase();
        $member = $this->createUserWithRole(Role::Mitglied);
        $covers = collect(range(1, 101))->map(function (int $number): BookCover {
            $book = Book::factory()->create([
                'type' => BookType::MaddraxDieDunkleZukunftDerErde,
                'roman_number' => 20_000 + $number,
            ]);

            return BookCover::factory()->for($book)->create();
        });

        foreach ($covers->take(99) as $cover) {
            CoverRating::factory()->for($member)->for($cover, 'bookCover')->create();
        }

        BaxxEarningRule::query()->updateOrCreate(
            ['action_key' => CoverRatingBaxxService::ACTION_KEY],
            [
                'label' => 'Cover-Bewertungen',
                'description' => 'MariaDB-Paralleltest',
                'points' => 1,
                'every_count' => 100,
                'is_active' => true,
            ],
        );

        $this->assertSame(1, DB::transactionLevel());
        DB::connection()->commit();
        $this->committedTestDatabase = true;

        $first = $this->startWorker($member->id, $covers[99]->id);
        $second = $this->startWorker($member->id, $covers[100]->id);

        try {
            $this->assertSame('ready', $this->readWorkerResult($first)['event'] ?? null);
            $this->assertSame('ready', $this->readWorkerResult($second)['event'] ?? null);
            $this->sendWorkerCommand($first, 'RATE');
            $this->sendWorkerCommand($second, 'RATE');
            $firstResult = $this->readWorkerResult($first);
            $secondResult = $this->readWorkerResult($second);
            $firstExitCode = $this->finishWorker($first);
            $secondExitCode = $this->finishWorker($second);
        } finally {
            $this->terminateWorker($first);
            $this->terminateWorker($second);
        }

        $this->assertSame(0, $firstExitCode, $this->workerFailure($firstResult));
        $this->assertSame(0, $secondExitCode, $this->workerFailure($secondResult));
        $this->assertTrue($firstResult['ok'] ?? false, $this->workerFailure($firstResult));
        $this->assertTrue($secondResult['ok'] ?? false, $this->workerFailure($secondResult));
        $awards = [
            (int) ($firstResult['awarded_baxx'] ?? -1),
            (int) ($secondResult['awarded_baxx'] ?? -1),
        ];
        sort($awards);

        $this->assertSame([0, 1], $awards);
        $this->assertSame(101, CoverRating::query()->where('user_id', $member->id)->count());
        $this->assertSame(1, (int) UserPoint::query()->where('user_id', $member->id)->sum('points'));
        $this->assertSame(101, (int) BaxxEarningProgress::query()
            ->where('user_id', $member->id)
            ->where('action_key', CoverRatingBaxxService::ACTION_KEY)
            ->value('processed_count'));
    }

    protected function tearDown(): void
    {
        try {
            if ($this->committedTestDatabase && isset($this->app)) {
                $exitCode = $this->artisan('migrate:fresh')->execute();

                if ($exitCode !== 0) {
                    throw new RuntimeException("Die MariaDB-Testdatenbank konnte nicht bereinigt werden (Exit {$exitCode}).");
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
        $database = (string) config("database.connections.{$connection}.database");

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Der echte Cover-Baxx-Nebenlaeufigkeitstest benoetigt MariaDB.');
        }

        if ($database !== 'omxfc_maddraxikon_test') {
            throw new RuntimeException("Unsichere Testdatenbank verweigert: {$database}.");
        }

        $version = (string) (DB::selectOne('SELECT VERSION() AS version')->version ?? '');

        if (! str_contains($version, 'MariaDB')) {
            $this->markTestSkipped('Der echte Cover-Baxx-Nebenlaeufigkeitstest laeuft nur auf MariaDB.');
        }

        if (! function_exists('proc_open')) {
            $this->markTestSkipped('proc_open wird fuer getrennte Worker-Prozesse benoetigt.');
        }
    }

    /** @return array{process: resource, stdin: resource, stdout: resource, stderr: resource} */
    private function startWorker(int $userId, int $coverId): array
    {
        $pipes = [];
        $process = proc_open(
            [
                PHP_BINARY,
                base_path('tests/Support/MariaDbCoverRatingConcurrencyWorker.php'),
                (string) $userId,
                (string) $coverId,
            ],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            base_path(),
            null,
            ['bypass_shell' => true],
        );

        if (! is_resource($process) || count($pipes) !== 3) {
            throw new RuntimeException('Der Cover-Bewertungs-Worker konnte nicht starten.');
        }

        stream_set_timeout($pipes[1], 20);

        return [
            'process' => $process,
            'stdin' => $pipes[0],
            'stdout' => $pipes[1],
            'stderr' => $pipes[2],
        ];
    }

    /** @param array{stdout: resource, stderr: resource} $worker */
    private function readWorkerResult(array $worker): array
    {
        $line = fgets($worker['stdout']);

        if ($line === false) {
            $stderr = trim((string) stream_get_contents($worker['stderr']));
            throw new RuntimeException('Der Cover-Bewertungs-Worker lieferte kein Ergebnis. '.$stderr);
        }

        try {
            $result = json_decode(trim($line), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Ungueltige Worker-Ausgabe: '.trim($line), previous: $exception);
        }

        if (! is_array($result)) {
            throw new RuntimeException('Der Cover-Bewertungs-Worker lieferte kein JSON-Objekt.');
        }

        return $result;
    }

    /** @param array{stdin: resource} $worker */
    private function sendWorkerCommand(array $worker, string $command): void
    {
        fwrite($worker['stdin'], $command.PHP_EOL);
        fflush($worker['stdin']);
    }

    /** @param array{process: resource, stdin: resource, stdout: resource, stderr: resource} $worker */
    private function finishWorker(array &$worker): int
    {
        foreach (['stdin', 'stdout', 'stderr'] as $pipe) {
            if (is_resource($worker[$pipe])) {
                fclose($worker[$pipe]);
            }
        }

        $exitCode = is_resource($worker['process']) ? proc_close($worker['process']) : -1;
        $worker = [];

        return $exitCode;
    }

    /** @param array<string, mixed> $worker */
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

        if (isset($worker['process']) && is_resource($worker['process'])) {
            $status = proc_get_status($worker['process']);

            if ($status['running'] ?? false) {
                proc_terminate($worker['process']);
            }

            proc_close($worker['process']);
        }

        $worker = [];
    }

    /** @param array<string, mixed> $result */
    private function workerFailure(array $result): string
    {
        return trim(implode(' ', array_filter([
            (string) ($result['exception'] ?? ''),
            (string) ($result['message'] ?? ''),
        ]))) ?: 'Der Cover-Bewertungs-Worker meldete keinen Fehlertext.';
    }
}
