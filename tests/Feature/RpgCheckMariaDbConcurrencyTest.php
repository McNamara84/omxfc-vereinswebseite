<?php

namespace Tests\Feature;

use App\Models\RpgCheck;
use App\Models\RpgCheckParticipant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\RpgCheckFixtures;
use Tests\TestCase;

class RpgCheckMariaDbConcurrencyTest extends TestCase
{
    use RefreshDatabase, RpgCheckFixtures;

    private bool $committed = false;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'mysql') {
            $this->markTestSkipped('Separate MariaDB-Konfiguration erforderlich.');
        }
        if (getenv('DB_DATABASE') !== 'omxfc_rpg_test') {
            throw new RuntimeException('Only the isolated omxfc_rpg_test database may be used.');
        }
        parent::setUp();
        $this->checkFixtures();
    }

    public function test_parallel_duplicate_rolls_persist_one_result(): void
    {
        $check = $this->createCheck();
        $input = ['action' => 'roll', 'actor' => $this->player->id, 'check' => $check->id, 'participant' => $check->participants[0]->id];
        $results = $this->parallel($input, $input);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame(1, RpgCheckParticipant::whereNotNull('rolled_at')->count());
        $this->assertSame(9, $check->fresh()->participants[0]->total);
    }

    public function test_parallel_sides_complete_one_comparison(): void
    {
        $check = $this->createCheck('opposed');
        $results = $this->parallel(
            ['action' => 'roll', 'actor' => $this->player->id, 'check' => $check->id, 'participant' => $check->participants[0]->id],
            ['action' => 'roll', 'actor' => $this->otherPlayer->id, 'check' => $check->id, 'participant' => $check->participants[1]->id],
        );
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame('awaiting_decision', $check->fresh()->status);
        $this->assertSame(2, RpgCheckParticipant::whereNotNull('rolled_at')->count());
    }

    public function test_cancel_and_roll_have_a_consistent_terminal_state(): void
    {
        $check = $this->createCheck();
        $results = $this->parallel(
            ['action' => 'roll', 'actor' => $this->player->id, 'check' => $check->id, 'participant' => $check->participants[0]->id],
            ['action' => 'cancel', 'actor' => $this->leader->id, 'check' => $check->id],
        );
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['ok'])));
        $fresh = $check->fresh();
        $this->assertContains($fresh->status, ['completed', 'cancelled']);
        $this->assertSame($fresh->status === 'completed', $fresh->participants[0]->rolled_at !== null);
    }

    public function test_deletion_and_roll_leave_no_open_orphan(): void
    {
        $check = $this->createCheck('opposed');
        $results = $this->parallel(
            ['action' => 'roll', 'actor' => $this->player->id, 'check' => $check->id, 'participant' => $check->participants[0]->id],
            ['action' => 'delete', 'actor' => $this->player->id, 'character' => $this->character->id],
        );
        $this->assertTrue($results[1]['ok']);
        $this->assertSame('cancelled', $check->fresh()->status);
        $this->assertNull($check->fresh()->participants[0]->rpg_character_id);
    }

    public function test_parallel_batch_retries_do_not_duplicate_requests(): void
    {
        $input = $this->checkInput('opposed');
        $input['mode'] = 'fixed';
        $input['difficulty'] = 10;
        $request = ['action' => 'create', 'actor' => $this->leader->id, 'input' => $input];
        $results = $this->parallel($request, $request);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertSame(2, RpgCheck::count());
        $this->assertSame(2, RpgCheckParticipant::count());
    }

    public function test_conflicting_tie_decisions_are_serialized(): void
    {
        $check = $this->createCheck('opposed');
        $this->dice([3, 4], [4, 3]);
        $this->rollCheck($check);
        $this->rollCheck($check, $this->otherPlayer, 2);
        $common = ['action' => 'resolve', 'actor' => $this->leader->id, 'check' => $check->id];
        $results = $this->parallel($common + ['resolution' => 'side_1'], $common + ['resolution' => 'side_2']);
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['ok'])));
        $this->assertSame('completed', $check->fresh()->status);
        $this->assertContains($check->fresh()->winner_position, [1, 2]);
    }

    private function parallel(array $first, array $second): array
    {
        $this->assertSame(1, DB::transactionLevel());
        DB::connection()->commit();
        $this->committed = true;
        $workers = [];
        try {
            foreach ([$first, $second] as $input) {
                $process = proc_open([PHP_BINARY, base_path('tests/Support/MariaDbRpgCheckWorker.php')], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
                if (! is_resource($process)) {
                    throw new RuntimeException('Worker could not start.');
                }
                stream_set_timeout($pipes[1], 60);
                $workers[] = compact('process', 'pipes', 'input');
            }
            foreach ($workers as $worker) {
                $this->assertSame("ready\n", fgets($worker['pipes'][1]));
            }
            foreach ($workers as $worker) {
                fwrite($worker['pipes'][0], json_encode($worker['input'], JSON_THROW_ON_ERROR)."\n");
                fflush($worker['pipes'][0]);
            }
            $results = [];
            foreach ($workers as $worker) {
                $line = fgets($worker['pipes'][1]);
                $this->assertNotFalse($line, stream_get_contents($worker['pipes'][2]));
                $results[] = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            foreach ($workers as $worker) {
                foreach ($worker['pipes'] as $pipe) {
                    fclose($pipe);
                }
                if (proc_get_status($worker['process'])['running']) {
                    proc_terminate($worker['process']);
                }
                proc_close($worker['process']);
            }
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
